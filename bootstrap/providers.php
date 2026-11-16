<?php

use App\Providers\AppServiceProvider;
use App\Providers\FinanceServiceProvider;
use App\Providers\ProjectsServiceProvider;
use App\Providers\ResourcesServiceProvider;
use App\Providers\SalesServiceProvider;
use App\Providers\SiteServiceProvider;

return [
    AppServiceProvider::class,
    SalesServiceProvider::class,
    ProjectsServiceProvider::class,
    FinanceServiceProvider::class,
    SiteServiceProvider::class,
    ResourcesServiceProvider::class,
];
