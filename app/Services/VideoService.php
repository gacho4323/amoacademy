<?php
namespace App\Services;

use App\Interfaces\VideoInterface;
use App\Models\Video;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoService
{
    private VideoInterface $videoRepository;

    public function __construct(VideoInterface $videoRepository)
    {
        $this->videoRepository = $videoRepository;
    }

    public function uploadVideo(UploadedFile $file, string $title, int $courseId, bool $isIntro = false): Video
    {
        return $this->videoRepository->uploadVideo($file, $title, $courseId, $isIntro);
    }

    public function concatenateVideos(Video $introVideo, Video $mainVideo): Video
    {
        // Get temporary URLs for the videos
        $introUrl = Storage::disk('r2')->temporaryUrl($introVideo->path, now()->addMinutes(30));
        $mainUrl  = Storage::disk('r2')->temporaryUrl($mainVideo->path, now()->addMinutes(30));

        // Create temporary local paths
        $tempDir = storage_path('app/temp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $introTempPath  = $tempDir . '/intro_' . uniqid() . '.mp4';
        $mainTempPath   = $tempDir . '/main_' . uniqid() . '.mp4';
        $outputTempPath = $tempDir . '/concatenated_' . uniqid() . '.mp4';

        try {
            // Download videos from R2 to local temporary files
            $introResponse = Http::get($introUrl);
            $mainResponse  = Http::get($mainUrl);

            if ($introResponse->failed() || $mainResponse->failed()) {
                throw new \Exception('Failed to download videos from R2');
            }

            file_put_contents($introTempPath, $introResponse->body());
            file_put_contents($mainTempPath, $mainResponse->body());

            // Verify files were downloaded
            if (! file_exists($introTempPath) || ! file_exists($mainTempPath)) {
                throw new \Exception('Failed to save temporary video files');
            }

            // Initialize FFProbe to get intro duration
            $ffprobe       = FFProbe::create();
            $introDuration = $ffprobe->format($introTempPath)->get('duration');
            Log::info('Intro duration: ' . $introDuration);

            // Use PHP-FFMpeg to concatenate videos instead of raw command
            $ffmpeg = FFMpeg::create();
            $video  = $ffmpeg->open($introTempPath);
            $video->concat([$introTempPath, $mainTempPath])
                ->saveFromSameCodecs($outputTempPath, true);

            // Verify the concatenated file exists
            if (! file_exists($outputTempPath)) {
                throw new \Exception('Concatenated video file was not created');
            }

            // Upload the concatenated video to R2
            $outputPath = 'videos/concatenated_' . uniqid() . '.mp4';
            Storage::disk('r2')->put($outputPath, file_get_contents($outputTempPath));

            // Create a new Video record for the concatenated video
            $concatenatedVideo = Video::create([
                'course_id'      => $mainVideo->course_id,
                'author_id'      => $mainVideo->author_id,
                'title'          => $mainVideo->title . ' (with Intro)',
                'path'           => $outputPath,
                'size'           => filesize($outputTempPath),
                'mime_type'      => 'video/mp4',
                'order'          => $mainVideo->order,
                'intro_duration' => $introDuration, // Store intro duration
            ]);

            return $concatenatedVideo;
        } catch (\Exception $e) {
            Log::error('Video concatenation error: ' . $e->getMessage());
            throw new \Exception('Failed to concatenate videos: ' . $e->getMessage());
        } finally {
            // Clean up temporary files
            @unlink($introTempPath);
            @unlink($mainTempPath);
            @unlink($outputTempPath);
        }
    }

    public function getAllVideos(): Collection
    {
        return $this->videoRepository->getAllVideos();
    }

    public function getVideoUrl(int $id): string
    {
        return $this->videoRepository->getVideoUrl($id);
    }

    public function getThumbnailUrl(int $id): string
    {
        return $this->videoRepository->getThumbnailUrl($id);
    }
}
