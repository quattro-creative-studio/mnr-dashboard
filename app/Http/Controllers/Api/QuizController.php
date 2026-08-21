<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\QuizCode;
use App\QuizInLanguage;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Carbon\Carbon;
use Illuminate\Http\Request;

class QuizController extends Controller {

    public function quizMakerWebhook(Request $request) {
        $json = $request->get('json');
        if(!$json) {
            return response('');
        }
        \Log::info("Received quiz maker webhook");

        $filename = 'quiz-maker-hooks/' . date('Y-m-d_H-i-s') . '.json';
        \Storage::put($filename, $json);
        Bugsnag::leaveBreadcrumb("quiz maker webhook saved as: $filename");

        $json = json_decode($json);

        $qID = $json->quiz->id;
        $qIL = QuizInLanguage::where('quiz_maker_id', $qID)->first();

        if (!$qIL) {
            \Log::error("Got quiz maker webhook but cannot find the quiz id in our database", ['quiz-maker-id' => $qID]);
            return response('');
        }

        foreach ($json->responses as $res) {
            $code = $res->unique_code->code;
            /** @var QuizCode $quizCode */
            $quizCode = $qIL->codes()->where('code', $code)->first();
            if (!$quizCode) {
                \Log::error("Given code cannot be found", ['code' => $code]);
                continue;
            }
            if ($quizCode->assignment->response()->exists()) {
                // continue, not return: this abandons ONE already-recorded code,
                // not the rest of the payload. quiz-maker retries a delivery by
                // replaying it in full, so a `return` here dropped every later
                // response in the batch -- silently, since the endpoint always
                // answers an empty 200 and never reports failure upstream.
                \Log::warning("Given code is already in database.. skipping it", ['code' => $code]);
                continue;
            }
            $quizCode->assignment->response()->updateOrCreate([
                'quizmaker_response_id' => $res->id,
            ], [
                'quizmaker_response_id' => $res->id,
                'score' => $res->score,
                // Timezone stated explicitly. Carbon 3 changed createFromTimestampMs()
                // to default to UTC, where Carbon 2 used the application timezone --
                // so this silently started writing responded_at an hour out.
                'responded_at' => Carbon::createFromTimestampMs($res->times->end, config('app.timezone')),
            ]);
        }


        return response('');
    }

}
