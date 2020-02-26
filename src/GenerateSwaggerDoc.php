<?php

declare(strict_types=1);

namespace Mtrajano\LaravelSwagger;

use Illuminate\Console\Command;
use ReflectionException;
use Storage;

class GenerateSwaggerDoc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger:generate
                            {--format=json : The format of the output, current options are json and yaml}
                            {--f|filters=* : Filter to a specific route prefix, such as /api or /v2/api}
                            {--o|output= : Output file to write the contents to, defaults to stdout}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically generates a swagger documentation file for this application';

    /**
     * @var array
     */
    protected array $config;

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws LaravelSwaggerException
     * @throws ReflectionException
     */
    public function handle()
    {
        $this->config = config('laravel-swagger');
        $filters = $this->getFilters();
        $file = $this->getOutputFile();

        $docs = (new Generator($this->config, $filters))->generate();

        $formattedDocs = (new FormatterManager($docs))
            ->setFormat($this->option('format'))
            ->format();

        if ($file) {
            $this->info('Writing generated data to file');
            Storage::disk($this->config['disk'])->put($file, $formattedDocs);
        } else {
            $this->line($formattedDocs);
        }
    }

    /**
     * @return array
     */
    protected function getFilters(): array
    {
        $filters = $this->option('filters');

        if (empty($filters)) {
            $filters = $this->config['filters'];
        }

        return (array)$filters;
    }

    /**
     * @return string
     */
    protected function getOutputFile(): ?string
    {
        $file = $this->option('output');

        if (($file === null) && $this->config['output'] === 'file') {
            $path = $this->config['path'];
            if (!Storage::disk($this->config['disk'])->exists($path)) {
                $this->info('Configured path [' . $path . '] does not exists, creating now.');
                Storage::disk($this->config['disk'])->makeDirectory($path)
                    ? $this->info('The path was created successfully')
                    : $this->info('The path could not be created.');
            }

            $file = implode(DIRECTORY_SEPARATOR, [$path, $this->config['file_name'] . '.' . $this->config['file_type']]);
        }

        return $file;
    }
}
