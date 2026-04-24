<?php

namespace App\Jobs;

use App\Models\Template;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class GenerateThumbnailTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Template $template;

    /**
     * Create a new job instance.
     */
    public function __construct(Template $template)
    {
        $this->template = $template;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $templatePath = $this->template->path;
        $temporaryUrl = Storage::disk('r2')->temporaryUrl($templatePath, now()->addMinutes(5));
        $thumbnailPath = 'thumbnails/' . pathinfo($templatePath, PATHINFO_FILENAME) . '.jpg';

        FFMpeg::fromDisk('r2')
            ->openUrl($temporaryUrl)
            ->getFrameFromSeconds(5) // Take a frame at 5 seconds
            ->export()
            ->toDisk('r2')
            ->save($thumbnailPath);
    }
}