<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Imports\RabImport;
use Maatwebsite\Excel\Facades\Excel;

class TestImport extends Command
{
    protected $signature = 'test:import {file} {project_id=1}';
    protected $description = 'Test Excel Import';

    public function handle()
    {
        $file = $this->argument('file');
        $projectId = $this->argument('project_id');

        $this->info("Importing $file for project $projectId...");

        try {
            Excel::import(new RabImport($projectId), $file);
            $this->info("Success!");
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
}
