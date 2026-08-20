<?php

namespace Tests\Feature;

use App\Certificate;
use App\PlaceHolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * Tripwire: placeholder resolution.
 *
 * Email bodies are edited in the admin UI and carry %TOKEN% placeholders.
 * Adding one means touching both getPlaceholders() (which drives the admin
 * preview) and getReplacement() (which does the actual substitution). A token
 * present in only one of the two renders as an empty string in real mail and
 * nothing complains -- exactly the silent drift this suite exists to catch.
 */
class PlaceHolderTest extends TestCase
{
    use RefreshDatabase, BuildsDomainFixtures;

    /**
     * Build a class wired up for every placeholder that needs state.
     */
    private function fullyWiredClass()
    {
        $teacher = $this->makeTeacher();
        $class = $this->makeClass($teacher, [
            'name' => '7ST1',
            'may_token' => 'may-token-abc',
            'party_token' => 'party-token-xyz',
        ]);

        Certificate::create([
            'school_class_id' => $class->id,
            'url' => "certificats/{$class->id}/certificat.pdf",
            'uid' => Uuid::uuid4()->toString(),
        ]);

        return [$teacher->fresh(), $class->fresh()];
    }

    public function testEveryAdvertisedPlaceholderResolvesToSomething()
    {
        [$teacher, $class] = $this->fullyWiredClass();
        $assignment = $this->makeQuizAssignment($class);

        $unresolved = [];

        foreach (PlaceHolder::getPlaceholders() as $placeholder) {
            $value = PlaceHolder::getReplacement(
                $placeholder->key,
                $teacher,
                $class,
                $assignment
            );

            if ($value === '') {
                $unresolved[] = $placeholder->key;
            }
        }

        $this->assertSame(
            [],
            $unresolved,
            'These placeholders are offered in the admin preview but resolve to an '
            .'empty string, so they render as nothing in real mail: '
            .implode(', ', $unresolved)
        );
    }

    public function testReplaceAllSubstitutesEveryPlaceholderInABody()
    {
        [$teacher, $class] = $this->fullyWiredClass();
        $assignment = $this->makeQuizAssignment($class);

        $keys = PlaceHolder::getPlaceholders()->pluck('key')->all();
        $body = 'Bonjour '.implode(' / ', $keys).' fin.';

        $result = PlaceHolder::replaceAll($body, $teacher, $class, $assignment);

        foreach ($keys as $key) {
            $this->assertStringNotContainsString(
                $key,
                $result,
                "Placeholder {$key} survived replaceAll() untouched."
            );
        }
    }

    public function testKnownPlaceholdersResolveToTheExpectedShape()
    {
        [$teacher, $class] = $this->fullyWiredClass();

        $this->assertSame(
            'Monsieur '.$teacher->first_name.' '.$teacher->last_name,
            PlaceHolder::getReplacement('%PROF%', $teacher, $class, null)
        );
        $this->assertSame('M. '.$teacher->first_name.' '.$teacher->last_name,
            PlaceHolder::getReplacement('%PROF_1%', $teacher, $class, null));
        $this->assertSame('Monsieur', PlaceHolder::getReplacement('%TITRE_LONG%', $teacher, $class, null));
        $this->assertSame('M.', PlaceHolder::getReplacement('%TITRE%', $teacher, $class, null));
        $this->assertSame('7ST1', PlaceHolder::getReplacement('%NOM_CLASSE%', $teacher, $class, null));

        $this->assertContains(
            $class->certificate->uid,
            PlaceHolder::getReplacement('%LIEN_CERTIFICAT%', $teacher, $class, null)
        );
        $this->assertContains(
            'party-token-xyz',
            PlaceHolder::getReplacement('%LIEN_FETE_OUI%', $teacher, $class, null)
        );
    }

    /**
     * The follow-up route is declared as /suivi/{token}/{stillNonSmoking} but
     * PlaceHolder::getReplacement() calls route('follow-up', [..., 'status' => ...]).
     * The names do not match. Laravel 5.7 tolerates this and fills the missing
     * segment positionally from the leftover value, producing /suivi/TOK/true.
     *
     * That is a property of the URL generator, not of this application, and it
     * has no test upstream protecting it. If a later Laravel throws
     * UrlGenerationException or emits /suivi/TOK?status=true instead, every
     * follow-up mail silently ships a broken link. Pin the current output.
     */
    public function testFollowUpLinksAreBuiltFromMismatchedRouteParameterNames()
    {
        [$teacher, $class] = $this->fullyWiredClass();

        $this->assertSame(
            url('/suivi/may-token-abc/true'),
            PlaceHolder::getReplacement('%SUIVI_OUI%', $teacher, $class, null)
        );
        $this->assertSame(
            url('/suivi/may-token-abc/false'),
            PlaceHolder::getReplacement('%SUIVI_NON%', $teacher, $class, null)
        );
        $this->assertSame(
            url('/party/party-token-xyz/true'),
            PlaceHolder::getReplacement('%LIEN_FETE_OUI%', $teacher, $class, null)
        );
    }

    public function testAnUnknownPlaceholderResolvesToAnEmptyString()
    {
        [$teacher, $class] = $this->fullyWiredClass();

        $this->assertSame(
            '',
            PlaceHolder::getReplacement('%NOT_A_REAL_TOKEN%', $teacher, $class, null)
        );
    }

    public function testCertificateLinkRefusesToRenderWithoutACertificate()
    {
        $teacher = $this->makeTeacher();
        $class = $this->makeClass($teacher);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('certificate must not be null');

        PlaceHolder::getReplacement('%LIEN_CERTIFICAT%', $teacher, $class, null);
    }
}
