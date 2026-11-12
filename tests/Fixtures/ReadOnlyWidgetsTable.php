<?php

namespace Tests\Fixtures;

class ReadOnlyWidgetsTable extends WidgetsTable
{
    protected bool $deletable = false;
}
