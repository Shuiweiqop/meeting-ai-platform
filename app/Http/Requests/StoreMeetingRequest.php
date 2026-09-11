<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meeting_date' => ['nullable', 'date'],
            'audio_file' => ['required', 'file', 'mimes:mp3,wav,m4a,ogg,mp4,mov,webm,mkv,avi', 'max:2097152'],
        ];
    }
}
