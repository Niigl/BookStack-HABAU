<?php
/**
 * Habau Theme Migration Script
 * Ausführen mit: php themes/habau/migrate.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$migrations = [
    '001_create_user_entity_permissions' => function() {
        if (!\Schema::hasTable('user_entity_permissions')) {
            \Schema::create('user_entity_permissions', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->integer('entity_id');
                $table->string('entity_type', 50);
                $table->integer('user_id');
                $table->tinyInteger('view')->default(0);
                $table->tinyInteger('create')->default(0);
                $table->tinyInteger('update')->default(0);
                $table->tinyInteger('delete')->default(0);
                $table->index(['entity_id', 'entity_type']);
                $table->index('user_id');
            });
            echo "✓ Tabelle 'user_entity_permissions' erstellt\n";
        } else {
            echo "→ Tabelle 'user_entity_permissions' existiert bereits\n";
        }
    },
];

// Migration-Status Tabelle erstellen falls nicht vorhanden
if (!\Schema::hasTable('habau_migrations')) {
    \Schema::create('habau_migrations', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->string('migration')->primary();
        $table->timestamp('run_at')->useCurrent();
    });
    echo "✓ Migration-Tracking Tabelle erstellt\n";
}

// Migrationen ausführen
foreach ($migrations as $name => $migration) {
    $alreadyRun = \DB::table('habau_migrations')->where('migration', $name)->exists();
    if (!$alreadyRun) {
        $migration();
        \DB::table('habau_migrations')->insert(['migration' => $name]);
        echo "✓ Migration '$name' ausgeführt\n";
    } else {
        echo "→ Migration '$name' bereits ausgeführt\n";
    }
}

echo "\nFertig!\n";