<?php

use App\Providers\AppServiceProvider;
use App\Providers\FinanceServiceProvider;
use App\Providers\ProjectsServiceProvider;
use App\Providers\SalesServiceProvider;

return [
    AppServiceProvider::class,
    SalesServiceProvider::class,
    ProjectsServiceProvider::class,
    FinanceServiceProvider::class,
];
