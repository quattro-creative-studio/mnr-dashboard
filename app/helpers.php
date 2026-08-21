<?php

function inputValidationClass(\Illuminate\Support\ViewErrorBag $errorBag, $fieldName) {
    if($errorBag->count() === 0)
        return ""; //no errors at all, no request sent

    $messages = $errorBag->getMessages();
    if(isset($messages[$fieldName]) && count($messages[$fieldName]) > 0)
        return "is-invalid";

    if(!isset($messages[$fieldName]) || count($messages[$fieldName]) == 0)
        return "is-valid";

    return "";
}

function inputValidationMessages(\Illuminate\Support\ViewErrorBag $errorBag, $fieldName) {
    $class = inputValidationClass($errorBag, $fieldName);
    if($class === "is-invalid") {
        $messages = $errorBag->getMessages();
        if(isset($messages[$fieldName])) {
            return new \Illuminate\Support\HtmlString(implode("<br>", $messages[$fieldName]));
        }
    }
    return "";
}

/**
 * Converts a nullable boolean to 3 different icons; null -> circle, true -> check, false -> cross
 * @param $status
 * @return \Illuminate\Support\HtmlString
 */
function statusToIcon($status) {
    if($status === null)
        return new \Illuminate\Support\HtmlString('<i class="fa fa-fw fa-circle text-info"></i>');
    if($status === 1)
        return new \Illuminate\Support\HtmlString('<i class="fa fa-fw fa-check-circle text-success"></i>');
    if($status === 0)
        return new \Illuminate\Support\HtmlString('<i class="fa fa-fw fa-times-circle text-danger"></i>');
    return new \Illuminate\Support\HtmlString("");
}

function isRegistrationOpen() {
    $start = \App\EditableDate::find(\App\EditableDate::TEACHER_INSCRIPTION_START);
    $end = \App\EditableDate::find(\App\EditableDate::TEACHER_INSCRIPTION_END);

    // Either bound missing means registration is not configured, so it is closed.
    // Carbon 2 reached the same answer by accident -- gte(null) was true and
    // lt(null) was false -- but Carbon 3 rejects null outright with a TypeError,
    // and this helper is called from layouts/app-sidebar, the shell of every
    // authenticated page. An absent date would have 500'd the entire application.
    if ($start === null || $end === null) {
        return false;
    }

    return \Carbon\Carbon::now()->gte($start) && \Carbon\Carbon::now()->lt($end);
}
