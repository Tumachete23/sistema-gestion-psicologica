<?php

namespace Tests\Feature;

use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Tests\TestCase;

class TenantArchitectureTest extends TestCase
{
    public function test_every_business_model_is_modular_and_implements_tenant_isolation(): void
    {
        $models = [];
        // Scan all app/, not a maintained list that could silently omit a new model.
        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }
            $models[] = $class;
            $this->assertStringStartsWith('App\\Modules\\', $class, "$class must live in a domain module.");
            $this->assertContains(BelongsToTenant::class, class_uses_recursive($class), "$class must implement BelongsToTenant.");
        }

        $this->assertGreaterThanOrEqual(3, count($models), 'The model scan must discover the tenant hierarchy.');
    }
}
