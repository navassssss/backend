<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Student;
use App\Models\User;
use App\Models\ClassRoom;
use App\Models\MonthlyFeePlan;
use App\Models\FeePayment;
use App\Models\FeePaymentAllocation;
use App\Services\FeeManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FeeManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeeManagementService $service;
    private Student $student;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new FeeManagementService();
        
        // Create test data
        $class = ClassRoom::create(['name' => 'Test Class']);
        
        $userRecord = User::create([
            'name' => 'Test Student',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);
        
        $this->student = Student::create([
            'user_id' => $userRecord->id,
            'class_id' => $class->id,
            'username' => 'test123',
            'roll_number' => '001',
        ]);
        
        $this->manager = User::create([
            'name' => 'Manager',
            'email' => 'manager@test.com',
            'password' => bcrypt('password'),
            'role' => 'manager',
        ]);
    }

    /** @test */
    public function it_can_process_payment_that_clears_exact_months()
    {
        // Create fee plans: Jan-Mar 2024, ₹500 each
        for ($month = 1; $month <= 3; $month++) {
            MonthlyFeePlan::create([
                'student_id' => $this->student->id,
                'year' => 2024,
                'month' => $month,
                'payable_amount' => 500,
                'reason' => 'Test fee',
            ]);
        }
        
        // Pay ₹1500 (clears all 3 months)
        $result = $this->service->processPayment(
            $this->student->id,
            1500,
            '2024-03-15',
            $this->manager->id
        );
        
        $this->assertCount(3, $result['allocations']);
        $this->assertTrue($result['allocations'][0]['cleared']);
        $this->assertTrue($result['allocations'][1]['cleared']);
        $this->assertTrue($result['allocations'][2]['cleared']);
    }

    /** @test */
    public function it_can_handle_partial_payment()
    {
        // Create fee plans: Jan-Feb 2024, ₹500 each
        for ($month = 1; $month <= 2; $month++) {
            MonthlyFeePlan::create([
                'student_id' => $this->student->id,
                'year' => 2024,
                'month' => $month,
                'payable_amount' => 500,
                'reason' => 'Test fee',
            ]);
        }
        
        // Pay ₹700 (clears Jan, partial Feb)
        $result = $this->service->processPayment(
            $this->student->id,
            700,
            '2024-02-15',
            $this->manager->id
        );
        
        $this->assertCount(2, $result['allocations']);
        $this->assertTrue($result['allocations'][0]['cleared']); // Jan cleared
        $this->assertFalse($result['allocations'][1]['cleared']); // Feb partial
        $this->assertEquals(200, $result['allocations'][1]['allocated']);
    }

    /** @test */
    public function it_auto_creates_future_months_for_overpayment()
    {
        // Create fee plan: Jan 2024, ₹500
        MonthlyFeePlan::create([
            'student_id' => $this->student->id,
            'year' => 2024,
            'month' => 1,
            'payable_amount' => 500,
            'reason' => 'Test fee',
        ]);
        
        // Pay ₹2000 (clears Jan + creates Feb-Apr)
        $result = $this->service->processPayment(
            $this->student->id,
            2000,
            '2024-01-15',
            $this->manager->id
        );
        
        $this->assertCount(4, $result['allocations']);
        $this->assertFalse($result['allocations'][0]['auto_created']); // Jan existing
        $this->assertTrue($result['allocations'][1]['auto_created']); // Feb auto-created
        $this->assertTrue($result['allocations'][2]['auto_created']); // Mar auto-created
        $this->assertTrue($result['allocations'][3]['auto_created']); // Apr auto-created
        
        // Verify fee plans were created
        $this->assertDatabaseHas('monthly_fee_plans', [
            'student_id' => $this->student->id,
            'year' => 2024,
            'month' => 2,
            'payable_amount' => 500,
        ]);
    }

    /** @test */
    public function it_can_set_class_fee_for_year()
    {
        $count = $this->service->setClassFeeForYear(
            $this->student->class_id,
            2024,
            600,
            'Standard fee'
        );
        
        $this->assertEquals(12, $count); // 12 months created
        
        $this->assertDatabaseHas('monthly_fee_plans', [
            'student_id' => $this->student->id,
            'year' => 2024,
            'month' => 1,
            'payable_amount' => 600,
        ]);
    }

    /** @test */
    public function it_calculates_monthly_status_correctly()
    {
        // Create fee plans
        MonthlyFeePlan::create([
            'student_id' => $this->student->id,
            'year' => 2024,
            'month' => 1,
            'payable_amount' => 500,
        ]);
        
        MonthlyFeePlan::create([
            'student_id' => $this->student->id,
            'year' => 2024,
            'month' => 2,
            'payable_amount' => 500,
        ]);
        
        // Pay ₹700
        $this->service->processPayment(
            $this->student->id,
            700,
            '2024-02-01',
            $this->manager->id
        );
        
        $status = $this->service->getStudentMonthlyStatus($this->student->id);
        
        $this->assertCount(2, $status);
        $this->assertEquals('paid', $status[0]['status']); // Jan paid
        $this->assertEquals('partial', $status[1]['status']); // Feb partial
        $this->assertEquals(200, $status[1]['paid']);
        $this->assertEquals(300, $status[1]['balance']);
    }
}
