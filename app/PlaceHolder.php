<?php

namespace App;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PlaceHolder {

    /**
     * @var string
     */
    public $key;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $previewValue;

    private function __construct(string $key, string $previewValue, string $description = "") {
        $this->key = '%' . $key . '%';
        $this->previewValue = $previewValue;
        $this->description = $description;
    }


    /**
     * Returns the text from this email where all placeholders have been replaced
     * @param string $text The text with the placeholders to be changed
     * @param Teacher $teacher
     * @param SchoolClass|null $class
     * @param QuizAssignment|null $assignment
     * @return string The complete email, with placeholders having been replaced.
     * @throws \Exception
     */
    public static function replaceAll(
        string $text,
        Teacher $teacher,
        ?SchoolClass $class,
        ?QuizAssignment $assignment = null
    ): string {
        foreach (PlaceHolder::getPlaceholders() as $placeholder) {
            /** @var PlaceHolder $placeholder */
            if (Str::contains($text, $placeholder->key)) {
                $text = str_replace(
                    $placeholder->key,
                    self::getReplacement($placeholder->key, $teacher, $class, $assignment),
                    $text);
            }
        }
        return $text;
    }

    /**
     * Replaces the subject with the proper value. Subject should not contain the placeholder delimiter (%).
     * @param string $subject
     * @param Teacher|null $teacher
     * @param SchoolClass|null $class
     * @param QuizAssignment|null $assignment
     * @return string Replaced text
     * @throws \Exception
     */
    public static function getReplacement(
        string $subject,
        ?Teacher $teacher,
        ?SchoolClass $class,
        ?QuizAssignment $assignment
    ) {
        $subject = str_replace('%', '', $subject);
        if ($subject === "PROF")
            return $teacher->salutation->long_form . ' ' . $teacher->first_name . ' ' . $teacher->last_name;
        if ($subject === "PROF_1")
            return $teacher->salutation->short_form . ' ' . $teacher->first_name . ' ' . $teacher->last_name;
        if ($subject === "TITRE_LONG")
            return $teacher->salutation->long_form;
        if ($subject === "TITRE")
            return $teacher->salutation->short_form;
        if ($subject === "PROF_PRENOM")
            return $teacher->first_name;
        if ($subject === "PROF_NOM")
            return $teacher->last_name;
        if ($subject === "NOM_CLASSE")
            return $class->name;
        if ($subject === "LIEN_LOGIN")
            return route('login');
        if ($subject === "LIEN_DOCUMENTS")
            return route('teacher.documents');
        if ($subject === "LIEN_FETE_INVITE")
            return route('teacher.party');
        if ($subject === "LIEN_CERTIFICAT") {
            if ($class->certificate == null)
                throw new \Exception("certificate must not be null");
            return route('certificate.download', [$class->certificate->uid]);
        }
        // The route declares {stillNonSmoking}, not {status}. Laravel 5.x filled the
        // missing segment positionally from the leftover value and produced the right
        // URL by accident; Laravel 6 raises UrlGenerationException instead. Same URL,
        // correctly named parameter.
        if ($subject === "SUIVI_OUI")
            return route('follow-up', ['token' => $class->getCurrentToken(), 'stillNonSmoking' => 'true']);
        if ($subject === "SUIVI_NON")
            return route('follow-up', ['token' => $class->getCurrentToken(), 'stillNonSmoking' => 'false']);
        if ($subject === "LIEN_FETE")
            return route('teacher.party');
        if ($subject === "LIEN_FETE_OUI")
            return route('party-response', ['token' => $class->party_token, 'status' => 'true']);
        if ($subject === "LIEN_FETE_NON")
            return route('party-response', ['token' => $class->party_token, 'status' => 'false']);
        if ($subject === "LIEN_QUIZ")
            return route('external.quiz.show', [$assignment->uuid]);

        return "";
    }

    public static function getPlaceholders(): Collection {
        return collect([
            new PlaceHolder("PROF", "Monsieur Max Mustermann"),
            new PlaceHolder("PROF_1", "M. Max Mustermann"),
            new PlaceHolder("TITRE_LONG", "Monsieur"),
            new PlaceHolder("TITRE", "M."),
            new PlaceHolder("PROF_PRENOM", "Max"),
            new PlaceHolder("PROF_NOM", "Mustermann"),
            new PlaceHolder("NOM_CLASSE", "7ST1", "Nom de la classe"),
            new PlaceHolder("LIEN_LOGIN", route('login'), "Lien login"),
            new PlaceHolder("LIEN_DOCUMENTS", route('teacher.documents'), "Lien documents"),
            new PlaceHolder("LIEN_FETE_INVITE", route('teacher.party'), "Lien invitation fête"),
            new PlaceHolder("LIEN_CERTIFICAT", "https://certificat.link/telecharger", "Lien télécharger certificat"),
            new PlaceHolder("SUIVI_OUI", "https://suivi.link/oui", "Lien réponse suivi oui"),
            new PlaceHolder("SUIVI_NON", "https://suivi.link/non", "Lien réponse suivi non"),
            new PlaceHolder("LIEN_FETE", "https://fete.link/", "Lien inscription fête"),
            new PlaceHolder("LIEN_FETE_OUI", "https://fete.link/oui", "Lien réponse fête oui"),
            new PlaceHolder("LIEN_FETE_NON", "https://fete.link/non", "Lien réponse fête non"),
            new PlaceHolder("LIEN_QUIZ", "https://quiz.link/", "Lien quiz"),
        ]);
    }

}
