<?php
namespace Config;
class Filters extends \CodeIgniter\Config\Filters
{
    public array $aliases = [
        'api' => \App\Shared\ApiFilter::class,
        'secureheaders' => \CodeIgniter\Filters\SecureHeaders::class,
    ];
    public array $required = ['before' => [], 'after' => []];
    public array $globals = ['before' => ['api'], 'after' => ['api', 'secureheaders']];
    public array $methods = [];
    public array $filters = [];
}
