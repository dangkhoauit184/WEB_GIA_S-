<?php
namespace App\Modules\People\Features\Assistants;

final class AssistantsController extends \App\Shared\ApiController
{
    public function workspace()
    {
        return $this->respond(fn() => AssistantsService::workspace());
    }

    public function session($id)
    {
        return $this->respond(fn() => AssistantsService::detail((int)$id));
    }

    public function checkin($id)
    {
        return $this->respond(fn() => AssistantsService::clock((int)$id, 'in', $this->input()));
    }

    public function checkout($id)
    {
        return $this->respond(fn() => AssistantsService::clock((int)$id, 'out', $this->input()));
    }

    public function attendance($id)
    {
        return $this->respond(fn() => AssistantsService::attendance((int)$id, $this->input()));
    }

    public function request()
    {
        return $this->respond(fn() => AssistantsService::request($this->input()), 201);
    }
}
