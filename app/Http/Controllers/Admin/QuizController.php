<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\QuizMail;
use App\PlaceHolder;
use App\Quiz;
use App\QuizAssignment;
use App\QuizInLanguage;
use App\Rules\QuizMakerUniqueRule;
use App\Rules\QuizMakerValidUrlRule;
use App\SchoolClass;
use Carbon\Carbon;
use Illuminate\Http\Request;

class QuizController extends Controller {

    private $languages = ['fr', 'de'];

    public function index() {
        $quizzes = Quiz::all();
        return view('admin.quiz', compact('quizzes'));
    }

    public function create() {
        $classes = SchoolClass::all();
        $quiz = new Quiz();
        $placeholders = EmailController::getPlaceholdersForView();

        return view('admin.quiz-create', compact('classes', 'quiz', 'placeholders'), ['languages' => $this->languages]);
    }

    public function createPost(Request $request) {
        $request->merge([
            'closes_at' => $request->get('closes_at_date') . ' ' . $request->get('closes_at_time'),
        ]);
        $rules = [
            'name' => 'required|string',
            'max_score' => 'required|int|min:1',
            'closes_at' => 'required|date|after:today',
            'classes' => 'array',
            'classes.*' => 'int|exists:school_classes,id',
            'email_text' => 'string|nullable',
        ];

        foreach ($this->languages as $lang) {
            $rules["quiz_url.$lang"] = ['required', 'url', new QuizMakerValidUrlRule, new QuizMakerUniqueRule];
        }

        $data = $this->validate($request, $rules);

        // Timezone stated explicitly: Carbon 3 defaults createFromTimestamp() to
        // UTC where Carbon 2 used the application timezone. Without this an admin
        // entering 18:00 gets 17:00 stored (16:00 in summer), and quiz:update --
        // which compares closes_at against MySQL's CURRENT_TIMESTAMP every minute --
        // closes the quiz an hour or two early with nothing reporting it.
        $data['closes_at'] = Carbon::createFromTimestamp(strtotime($data['closes_at']), config('app.timezone'));
        $data['email_text'] = $data['email_text'] ?? "";
        $quiz = Quiz::create($data);

        foreach ($data['quiz_url'] as $lang => $url) {
            $quiz->quizInLanguage()->create([
                'language' => $lang,
                'quiz_maker_id' => QuizMakerValidUrlRule::extractIdFromUrl($url),
            ]);
        }

        foreach ($data['classes'] as $class) {
            $quiz->assignments()->create([
                'school_class_id' => $class,
            ]);
        }

        return redirect()->route('admin.quiz.show', [$quiz]);
    }

    public function delete(Quiz $quiz)
    {
        $quiz->delete();
        \Session::flash('message', sprintf('Quiz %s supprimé avec succès', $quiz->name));
        return redirect()->back();
    }

    public function edit(Quiz $quiz) {
        abort_if($quiz->state === Quiz::STATE_CLOSED, 403);
        $classes = SchoolClass::all();
        $placeholders = EmailController::getPlaceholdersForView();
        return view('admin.quiz-create', compact('quiz', 'classes', 'placeholders'), ['languages' => $this->languages]);
    }

    public function editPost(Request $request, Quiz $quiz) {
        abort_if($quiz->state === Quiz::STATE_CLOSED, 403);
        $request->merge([
            'closes_at' => $request->get('closes_at_date') . ' ' . $request->get('closes_at_time'),
        ]);
        $data = $this->validate($request, [
            'name' => 'required|string',
            'max_score' => 'required|int|min:1',
            'closes_at' => 'required|date|date_format:Y-m-d H:i|after:today',
            'email_text' => 'string|nullable',
            'classes' => 'array',
            'classes.*' => 'int|exists:school_classes,id',
        ]);
        // Timezone stated explicitly: Carbon 3 defaults createFromTimestamp() to
        // UTC where Carbon 2 used the application timezone. Without this an admin
        // entering 18:00 gets 17:00 stored (16:00 in summer), and quiz:update --
        // which compares closes_at against MySQL's CURRENT_TIMESTAMP every minute --
        // closes the quiz an hour or two early with nothing reporting it.
        $data['closes_at'] = Carbon::createFromTimestamp(strtotime($data['closes_at']), config('app.timezone'));
        $data['email_text'] = $data['email_text'] ?? "";
        if($quiz->state === Quiz::STATE_CLOSED) {
            unset($data['email_text']);
        }
        $quiz->update($data);

        // Update class assignments
        $givenClasses = $request->get('classes');
        if($givenClasses != null) {
            $quiz->assignments()->whereNotIn('school_class_id', $givenClasses)->delete();

            $toAdd = collect($givenClasses)->diff($quiz->assignments()->pluck('school_class_id'));
            foreach ($toAdd as $class) {
                $quiz->assignments()->create([
                    'school_class_id' => $class,
                ]);
            }
        }

        return redirect()->route('admin.quiz.show', [$quiz]);
    }

    public function show(Quiz $quiz) {
        return view('admin.quiz-show', compact('quiz'));
    }

    public function codes(Quiz $quiz) {
        abort_if($quiz->state !== Quiz::STATE_NEW, 403);
        return view('admin.quiz-codes', compact('quiz'), ['languages' => $this->languages]);
    }

    public function createCodes(Request $request, Quiz $quiz) {
        abort_if($quiz->state !== Quiz::STATE_NEW, 403);

        foreach ($this->languages as $lang) {
            $rules["files.$lang"] = ['required', 'file', 'mimes:txt'];
        }
        $this->validate($request, $rules);

        $assignments = $quiz->assignments()->pluck('id');

        // validate code count <=> assignment cound
        $codesForLanguage = [];
        $errors = [];
        foreach ($this->languages as $lang) {
            $content = $request->file("files.$lang")->get();
            $codesForLanguage[$lang] = collect(explode("\r\n", $content));

            $given = sizeof($codesForLanguage[$lang]);
            $required = $assignments->count();
            if($given < $required) {
                $errors["files.$lang"] = ["Ce fichier n'a pas assez de codes uniques. $given < $required"];
            }
        }
        if($errors) {
            return back()->withErrors($errors);
        }

        // delete prev codes
        $quiz->assignments->each(fn (QuizAssignment $ass) => $ass->codes()->delete());

        // create codes for each language
        foreach ($codesForLanguage as $lang => $codes) {
            /** @var QuizInLanguage $qIL */
            $qIL = $quiz->quizInLanguage()
                ->where('language', $lang)
                ->first();

            $codes = $codes->take($assignments->count())->toArray();
            $zip = $assignments->zip($codes);

            foreach ($zip as $code) {
                $qIL->codes()
                    ->create([
                        'quiz_assignment_id' => $code->get(0),
                        'code' => $code->get(1),
                    ]);
            }
        }

        return redirect()->route('admin.quiz.show', [$quiz]);
    }

    public function review(Quiz $quiz)
    {
        abort_if($quiz->state !== Quiz::STATE_NEW, 403);
        $quiz->load('assignments', 'assignments.schoolClass.school', 'assignments.schoolClass.teacher');
        return view('admin.quiz-review', compact('quiz'));
    }

    public function send(Quiz $quiz)
    {
        if(!empty($quiz->validate())) {
            abort(422);
        }

        $assignments = $quiz->assignments;
        foreach ($assignments as $a) {
            \Mail::to($a->schoolClass->teacher->user->email)
                ->queue(new QuizMail($a));
        }

        \Session::flash('message', sprintf('Envoyé %d emails', $assignments->count()));

        $quiz->update([
            'state' => Quiz::STATE_RUNNING,
            'sent_at' => now(),
        ]);

        return redirect()->route('admin.quiz.show', [$quiz]);
    }

    public function sendReminders(Quiz $quiz)
    {
        if(!empty($quiz->validate(Quiz::STATE_RUNNING))) {
            abort(422);
        }

        $assignments = $quiz->assignments()->whereDoesntHave('response')->get();
        foreach ($assignments as $a) {
            \Mail::to($a->schoolClass->teacher->user->email)
                ->queue(new QuizMail($a, true));
        }

        \Session::flash('message', sprintf('Envoyé %d emails', $assignments->count()));

        return redirect()->route('admin.quiz.show', [$quiz]);
    }

    public function previewMail(Quiz $quiz)
    {
        $class = $quiz->assignments()->first()->schoolClass;
        $text = PlaceHolder::replaceAll($quiz->email_text, $class->teacher, $class, $quiz->assignments()->first());
        return view('emails.custom', [
            'text' => $text,
        ]);
    }

}
