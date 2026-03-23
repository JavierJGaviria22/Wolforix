<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class CleanOldAudios extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audios:clean {--minutes=10 : Eliminar audios más antiguos que X minutos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina audios temporales más antiguos que el tiempo especificado';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $minutes = (int) $this->option('minutes');
        $now = time();
        $cutoffTime = $now - ($minutes * 60);
        
        $audiosPath = storage_path('app/public/audios');
        
        // Verificar si la carpeta existe
        if (!File::isDirectory($audiosPath)) {
            $this->info('📁 Carpeta de audios no existe aún.');
            return 0;
        }

        $files = File::files($audiosPath);
        $deletedCount = 0;
        $totalCount = count($files);

        foreach ($files as $file) {
            $fileTime = filemtime($file->getRealPath());

            if ($fileTime < $cutoffTime) {
                File::delete($file->getRealPath());
                $deletedCount++;
                $this->line("🗑️  Eliminado: {$file->getFilename()}");
            }
        }

        $this->info("✅ Proceso completado: {$deletedCount}/{$totalCount} audios eliminados (más antiguos que {$minutes} minutos)");

        return 0;
    }
}
