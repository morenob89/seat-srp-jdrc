<?php

namespace CryptaTech\Seat\SeatSrp\Validation;

use Illuminate\Foundation\Http\FormRequest;

class AddKillMail extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'srpCharacterName' => 'required|string',
            'srpKillId' => 'unique:cryptatech_seat_srp_srp,kill_id|required|integer',
            'srpKillToken' => 'required|string',
            'srpCost' => 'numeric',
            'srpShipType' => 'string',
            'srpTypeId' => 'required|integer',
            'srpPingContent' => 'nullable|string',
            'srpQuoteID' => 'required|int|exists:cryptatech_seat_quotes,id',
            'srpOpType' => 'nullable|string',
            'srpClass' => 'nullable|string',
        ];
    }
}
