<?php

namespace App\Http\Requests;

use App\User;
use Illuminate\Foundation\Http\FormRequest;

class AdminTeacherUpdateRequest extends FormRequest {

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() {
        return \Auth::user() !== null && \Auth::user()->type === User::TYPE_ADMIN;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        $user_id = $this->route('teacher')->user->id;
        return [
            'salutation_id' => 'integer|required|exists:salutations,id',
            'last_name' => 'string|required',
            'first_name' => 'string|required',
            'phone' => 'string|required',
            'email' => 'email|required|unique:users,email,' . $user_id,
        ];
    }
}
