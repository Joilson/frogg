<?php

namespace Frogg\Services;

class DefaultService implements ApplicationServiceInterface
{
    public static function getName()
    {
        return 'defaultFroggService';
    }
}