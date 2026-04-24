<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,
    Spatie\Activitylog\ActivitylogServiceProvider::class,
];
