<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A checklist link: a web address (http, https) or a file on this Mac
 * (file:///Users/…). Laravel's `url` rule wants a host, which file:/// has not.
 */
class LinkUrl implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isLocalFile = is_string($value) && preg_match('#^file:///[^\s]+$#i', $value) === 1;
        $isWebAddress = Validator::make(['link' => $value], ['link' => 'url:http,https'])->passes();

        if (! $isLocalFile && ! $isWebAddress) {
            $fail('The :attribute must be a web address or a file:/// link.');
        }
    }
}
