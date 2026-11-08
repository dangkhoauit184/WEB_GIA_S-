<?php
namespace App\Modules\People\Features\Tutors;

// Gia sư shares the professional profile and business rules with teachers.
final class TutorsController extends \App\Modules\People\Features\Teachers\TeachersController
{
    protected ?string $type='gia_su';
}
