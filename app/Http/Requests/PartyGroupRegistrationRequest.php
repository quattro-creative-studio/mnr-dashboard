<?php

namespace App\Http\Requests;

use App\User;
use Illuminate\Foundation\Http\FormRequest;

class PartyGroupRegistrationRequest extends FormRequest {
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() {
        return (\Auth::user() !== null && \Auth::user()->type === User::TYPE_TEACHER && \Auth::user()->teacher !== null)
            || (\Auth::user() !== null && \Auth::user()->type === User::TYPE_ADMIN);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        return [
            'class' => 'array|min:1|max:3',
            'class.0.name' => 'required|string',
            'class.0.language' => 'required|string',
            'class.0.students' => 'required|numeric|min:3|max:10',
            'class.1.name' => 'nullable|string',
            'class.1.language' => 'nullable|string',
            'class.1.students' => 'nullable|numeric|min:3|max:10',
            'class.2.name' => 'nullable|string',
            'class.2.language' => 'nullable|string',
            'class.2.students' => 'nullable|numeric|min:3|max:10',
        ];
    }
}
