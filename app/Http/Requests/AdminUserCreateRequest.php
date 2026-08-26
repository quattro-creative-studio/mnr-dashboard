<?php

namespace App\Http\Requests;

use App\User;
use Illuminate\Foundation\Http\FormRequest;

class AdminUserCreateRequest extends FormRequest {
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() {
        return \Auth::user() != null && \Auth::user()->type === User::TYPE_ADMIN;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        return [
            // No password: the invitee sets their own through the reset form,
            // so nobody has to type one for someone else and send it onward.
            'email' => 'required|email|unique:users,email',
        ];
    }
}
