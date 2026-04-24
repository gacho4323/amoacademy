<?php

namespace App\Jobs;

use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Illuminate\Support\Facades\Storage;

class GenerateThumbnailVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Video $video;

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video)
    {
        $this->video = $video;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $videoPath = $this->video->path;
        $temporaryUrl = Storage::disk('r2')->temporaryUrl($videoPath, now()->addMinutes(5));
        $thumbnailPath = 'thumbnails/' . pathinfo($videoPath, PATHINFO_FILENAME) . '.jpg';

        FFMpeg::fromDisk('r2')
            ->openUrl($temporaryUrl)
            ->getFrameFromSeconds(5) // Take a frame at 5 seconds
            ->export()
            ->toDisk('r2')
            ->save($thumbnailPath);
    }
}