<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Group;
use App\Models\ImportBatch;
use App\Models\ImportAnomaly;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CsvImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed users and group memberships
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_csv_import_pipeline(): void
    {
        $user = User::where('email', 'aisha@flat4b.test')->first();
        $group = Group::where('name', 'Flat 4B')->first();

        $this->assertNotNull($user);
        $this->assertNotNull($group);

        // Path to the copied CSV file
        $csvPath = base_path('expenses_export.csv');
        $this->assertFileExists($csvPath);

        // Create an UploadedFile instance
        $file = new UploadedFile(
            $csvPath,
            'expenses_export.csv',
            'text/csv',
            null,
            true // Mark as test file
        );

        // Hit the import route
        $response = $this->actingAs($user)
            ->post(route('import.run', $group), [
                'csv_file' => $file,
            ]);

        // Expect redirect to import report
        $response->assertStatus(302);
        
        $batch = ImportBatch::latest()->first();
        $this->assertNotNull($batch);
        $response->assertRedirect(route('import.report', $batch));

        // Verify status is done
        $this->assertEquals('done', $batch->status);
        $this->assertEquals(42, $batch->row_count); // 43 lines in CSV - 1 header = 42 rows

        // Verify some items in database
        $this->assertGreaterThan(0, Expense::count());
        $this->assertGreaterThan(0, Settlement::count());
        $this->assertGreaterThan(0, ImportAnomaly::count());

        // Let's print the counts to the test console
        echo "\nImport completed successfully!";
        echo "\nTotal Expenses: " . Expense::count();
        echo "\nTotal Settlements: " . Settlement::count();
        echo "\nTotal Anomalies: " . ImportAnomaly::count() . "\n";
    }
}
