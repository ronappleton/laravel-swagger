<?php

declare(strict_types=1);

namespace Mtrajano\LaravelSwagger;

use Illuminate\Console\Command;
use Storage;
use ZipArchive;
use GuzzleHttp\Client;
use File;
use Artisan;

class Ui extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger:ui';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download and deploy swagger-ui to your Laravel app.';

    protected $config;

    public function __construct()
    {
        $this->config = config('laravel-swagger');
        parent::__construct();
    }

    public function handle(): void
    {
        if (!$this->createDirectories()) {
            $this->error('Unable to create the download directory.');
        }

        $this->downloadZip();
        $this->extractZip();
        $this->moveFiles();
        $this->cleanUp();
        $this->fixIndex();

        if ($this->config['post_ui_generate']) {
            $this->info('Generating swagger.json file.');
            Artisan::call('swagger:generate');
            $this->info('Process complete. You can now go to `' . config('app.url') . '/swagger`');
            $this->info('To see the results.');
        } else {
            $this->info('Process complete, after running `php artisan swagger:generate`');
            $this->info('You will be able to go to `' . config('app.url') . '/swagger`');
            $this->info('To view your documentation.');
        }
    }

    protected function createDirectories(): bool
    {
        if (!Storage::disk($this->config['disk'])->makeDirectory(public_path('swagger'))) {
            $this->error('Unable to create the public folder.');
            exit(0);
        }

        if (!Storage::disk('swagger')->makeDirectory(storage_path('swagger'))) {
            $this->error('Unable to create storage directory.');
            exit(0);
        }

        return true;
    }

    protected function downloadZip(): void
    {
        $guzzle = new Client();
        $response = $guzzle->get($this->config['swagger_ui_dist_location']);

        if (!Storage::disk('swagger')->put(
            storage_path('swagger') . DIRECTORY_SEPARATOR . '/swagger-ui.zip',
            $response->getBody()
        )) {
            $this->error('Could not store zip file.');
            exit(0);
        }
    }

    protected function extractZip(): void
    {
        $zip = new ZipArchive();

        if (!$zip->open(storage_path('swagger') . '/swagger-ui.zip')) {
            $this->error('Zip file invalid.');
            exit(0);
        }

        if (!$zip->extractTo(storage_path('swagger'))) {
            $this->error('Could not decompress zip file.');
            exit(0);
        }

        $zip->close();
    }

    protected function moveFiles()
    {
        foreach (Storage::disk('swagger')->files(storage_path('swagger') . '/swagger-ui-master/dist') as $file) {
            $pathParts = explode(DIRECTORY_SEPARATOR, $file);
            Storage::disk('swagger')->delete(public_path('swagger') . DIRECTORY_SEPARATOR . end($pathParts));
            Storage::disk('swagger')->move($file, public_path('swagger') . DIRECTORY_SEPARATOR . end($pathParts));
        }
    }

    protected function cleanUp()
    {
        Storage::disk('swagger')->deleteDirectory(storage_path('swagger'));
    }

    private function fixIndex()
    {
        $replacements = [
            './swagger-ui.css' => '/swagger/swagger-ui.css',
            './favicon-32x32.png' => '/swagger/favicon-32x32.png',
            './favicon-16x16.png' => '/swagger/favicon-16x16.png',
            './swagger-ui-bundle.js' => '/swagger/swagger-ui-bundle.js',
            './swagger-ui-standalone-preset.js' => '/swagger/swagger-ui-standalone-preset.js',
            'https://petstore.swagger.io/v2/swagger.json' => '/swagger.json',
        ];

        $html = File::get(public_path('swagger') . DIRECTORY_SEPARATOR . 'index.html');

        foreach ($replacements as $search => $replace) {
            $html = str_replace($search, $replace, $html);
        }

        File::put(public_path('swagger') . DIRECTORY_SEPARATOR . 'index.html', $html);
    }
}
