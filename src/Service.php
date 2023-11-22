<?php

namespace Enna\Trace;

use Enna\Framework\Service as BaseService;

class Service extends BaseService
{
    public function register(): void
    {
        $this->app->middleware->add(TraceDebug::class);
    }
}