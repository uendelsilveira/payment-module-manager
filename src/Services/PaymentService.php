<?php
/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 27/10/2025 13:59:40
*/

namespace Us\PaymentModuleManager\Services;

use App\Models\Payment;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use US\PaymentModuleManager\Repositories\PaymentRepository;

class PaymentService
{
    protected $paymentRepository;

    public function __construct(PaymentRepository $paymentRepository)
    {
        $this->paymentRepository = $paymentRepository;
    }

    public function createPayment(array $data): Payment
    {
        DB::beginTransaction();

        try {
            $payment = $this->paymentRepository->create($data);
            DB::commit();

            return $payment;
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    public function getPaymentById(int $id): ?Payment
    {
        return $this->paymentRepository->find($id);
    }

    public function updatePayment(int $id, array $data): ?Payment
    {
        DB::beginTransaction();

        try {
            $payment = $this->paymentRepository->update($id, $data);
            DB::commit();

            return $payment;
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    public function deletePayment(int $id): bool
    {
        DB::beginTransaction();

        try {
            $deleted = $this->paymentRepository->delete($id);
            DB::commit();

            return $deleted;
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    public function getPaymentsByUser(User $user)
    {
        return $this->paymentRepository->getPaymentsByUserId($user->id);
    }
}
