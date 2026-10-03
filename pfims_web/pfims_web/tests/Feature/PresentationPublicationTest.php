<?php

namespace Tests\Feature;

use App\Services\MLService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PresentationPublicationTest extends TestCase
{
    public function test_demonstration_publication_cannot_replace_a_model_using_operational_records(): void
    {
        Schema::create('project_tbl', function (Blueprint $table) {
            $table->increments('project_id');
            $table->string('data_source');
        });
        DB::table('project_tbl')->insert(['data_source' => 'operational']);
        $path = storage_path('framework/testing/demonstration-rejected-'.uniqid().'.phpml');
        file_put_contents($path, 'preserved estimator');
        try {
            (new MLService($path, loadModel: false))->publishPresentationCandidate([
                'status' => 'evaluated', 'cohort_policy' => 'presentation_progress',
                'data_sources' => ['company_inspired_sample'],
            ], 'support_vector_regression_rbf');
            $this->fail('Operational records must reject demonstration publication.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('exclusively presentation', $error->getMessage());
            $this->assertSame('preserved estimator', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    public function test_demonstration_publication_rejects_missing_evaluation_without_creating_an_artifact(): void
    {
        $path = storage_path('framework/testing/demonstration-missing-'.uniqid().'.phpml');
        try {
            (new MLService($path, loadModel: false))->publishPresentationCandidate([], 'support_vector_regression_rbf');
            $this->fail('A completed scoped evaluation is required.');
        } catch (RuntimeException $error) {
            $this->assertFileDoesNotExist($path);
        }
    }
}
