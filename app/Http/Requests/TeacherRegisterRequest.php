<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeacherRegisterRequest extends FormRequest {
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() {
        return \Auth::user() === null && isRegistrationOpen();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        return [
            'teacher_salutation' => 'required|exists:salutations,id',
            'teacher_first_name' => 'required|string',
            'teacher_last_name' => 'required|string',
            'teacher_email' => 'required|email|unique:users,email',
            'teacher_password' => 'required|string|min:8|confirmed',
            'teacher_phone' => 'required|string',
            'data_protection' => 'required|accepted',
        ];
    }
}
