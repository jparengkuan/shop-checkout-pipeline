<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * The application kernel. The MicroKernelTrait loads bundles, services and routes from config/.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
