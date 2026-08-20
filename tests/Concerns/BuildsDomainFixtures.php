<?php

namespace Tests\Concerns;

use App\Quiz;
use App\QuizAssignment;
use App\QuizCode;
use App\QuizInLanguage;
use App\SchoolClass;
use App\Teacher;
use App\User;
use Carbon\Carbon;

/**
 * Fixture builders for the characterisation suite.
 *
 * Migrations already seed salutations, schools, editable emails and editable
 * dates, so the factories can rely on those existing.
 */
trait BuildsDomainFixtures
{
    protected function makeTeacher(array $attributes = []): Teacher
    {
        $user = factory(User::class)->create($attributes);

        return $user->teacher;
    }

    protected function makeClass(Teacher $teacher = null, array $attributes = []): SchoolClass
    {
        $teacher = $teacher ?: $this->makeTeacher();

        return factory(SchoolClass::class)->create(array_merge([
            'teacher_id' => $teacher->id,
        ], $attributes));
    }

    /**
     * Attach $count answered quiz responses to a class.
     *
     * Eligibility is counted from quiz_responses, so this is the only lever
     * that decides party and certificate access.
     */
    protected function giveQuizResponses(SchoolClass $class, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $assignment = $this->makeQuizAssignment($class, $i);

            $assignment->response()->create([
                'quizmaker_response_id' => 9000 + $i,
                'score' => 5,
                'responded_at' => Carbon::now(),
            ]);
        }
    }

    protected function makeQuizAssignment(SchoolClass $class, int $seed = 0): QuizAssignment
    {
        $quiz = Quiz::create([
            'name' => "Quiz {$seed}",
            'email_text' => 'Texte du quiz',
            'max_score' => 10,
        ]);

        return QuizAssignment::create([
            'quiz_id' => $quiz->id,
            'school_class_id' => $class->id,
        ]);
    }

    /**
     * Build the full quiz-maker chain a webhook payload has to match against:
     * quiz -> quiz_in_language (holds quiz_maker_id) -> assignment -> code.
     */
    protected function makeQuizCode(SchoolClass $class, string $quizMakerId, string $code): QuizCode
    {
        $assignment = $this->makeQuizAssignment($class);

        $language = QuizInLanguage::create([
            'language' => 'fr',
            'quiz_id' => $assignment->quiz_id,
            'quiz_maker_id' => $quizMakerId,
        ]);

        return QuizCode::create([
            'quiz_assignment_id' => $assignment->id,
            'quiz_in_language_id' => $language->id,
            'code' => $code,
        ]);
    }
}
