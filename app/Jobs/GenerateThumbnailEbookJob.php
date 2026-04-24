<?php

namespace App\Jobs;

use App\Models\Ebook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class GenerateThumbnailEbookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Ebook $ebook;

    /**
     * Create a new job instance.
     */
    public function __construct(Ebook $ebook)
    {
        $this->ebook = $ebook;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $ebookPath = $this->ebook->path;
        $temporaryUrl = Storage::disk('r2')->temporaryUrl($ebookPath, now()->addMinutes(5));
        $thumbnailPath = 'thumbnails/' . pathinfo($ebookPath, PATHINFO_FILENAME) . '.jpg';

        FFMpeg::fromDisk('r2')
            ->openUrl($temporaryUrl)
            ->getFrameFromSeconds(5) // Take a frame at 5 seconds
            ->export()
            ->toDisk('r2')
            ->save($thumbnailPath);
    }
}