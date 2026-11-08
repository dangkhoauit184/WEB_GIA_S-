<?php
namespace App\Controllers;

final class Portal extends \CodeIgniter\Controller
{
    public function index(): string
    {
        return view('App\Modules\Administration\Features\Auth\Views\login');
    }
}
