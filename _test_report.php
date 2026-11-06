<?php
require __DIR__.'/vendor/autoload.php';
$db = new Illuminate\Database\Capsule\Manager();
$db->addConnection(require __DIR__.'/config/database.php');
$db->setAsGlobal();
$db->bootEloquent();
try {
    $o = App\Models\Order::first();
    echo 'Order OK: '.($o ? $o->id : 'none').PHP_EOL;
} catch (\Throwable $e) { echo 'Order error: '.$e->getMessage().PHP_EOL; }
try {
    $e = App\Models\Expense::first();
    echo 'Expense OK'.PHP_EOL;
} catch (\Throwable $ex) { echo 'Expense error: '.$ex->getMessage().PHP_EOL; }
