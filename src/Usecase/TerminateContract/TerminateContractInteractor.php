<?php

namespace App\Usecase\TerminateContract;

use App\Repository\Exception\ContractNotFoundException;
use App\Repository\Exception\DatabaseUnreachableException;
use App\Repository\Exception\ObjectNotFoundException;
use App\Repository\Exception\RisksNotFoundException;
use App\Usecase\BaseInteractor;
use App\Usecase\BaseResponse;
use App\Usecase\ResultCodes;
use App\Usecase\TerminateContract\Exceptions\ContractCanNotBeTerminated;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class TerminateContractInteractor extends BaseInteractor
{
    private const PARAM_NAME_DATE = 'toDate';

    /**
     * @param Request $request
     * @return BaseResponse
     */
    public function execute(Request $request): BaseResponse
    {
        $code = ResultCodes::SUCCESS;

        try {
            $this->validateRequest($request);

            $contractNumber = (int)$request->get('contractNumber');
            $this->validateContract($contractNumber);

            $terminationDate = $request->get(self::PARAM_NAME_DATE);
            $this->validateTerminationDate($terminationDate);

            $this->getRepository()->terminateContractByNumber($contractNumber, $terminationDate);
        } catch (Throwable $exception) {
            $code = $exception->getCode();
        }

        return new BaseResponse($code);
    }

    /**
     * @inheritDoc
     */
    protected function validateRequest(Request $request): void
    {
        $this->validateContractNumber($request);
        $this->validateDate($request, self::PARAM_NAME_DATE);
    }

    /**
     * @param int $contractNumber
     * @throws ContractCanNotBeTerminated
     * @throws ContractNotFoundException
     * @throws DatabaseUnreachableException
     * @throws ObjectNotFoundException
     * @throws RisksNotFoundException
     */
    private function validateContract(int $contractNumber): void
    {
        /**
         * @note this is a stateless example.
         * $contracts should be read from the session or other cache layer, not from the database.
         */
        $contract = $this->getRepository()->getContractByNumber($contractNumber, true);

        if ($contract->isInactive()) {
            throw new ContractCanNotBeTerminated(ResultCodes::CONTRACT_ALREADY_INACTIVE);
        }

        if ($contract->isTerminated()) {
            throw new ContractCanNotBeTerminated(ResultCodes::CONTRACT_ALREADY_TERMINATED);
        }

        if ($contract->isFinished()) {
            throw new ContractCanNotBeTerminated(ResultCodes::CONTRACT_ALREADY_FINISHED);
        }
    }

    /**
     * @param string $date
     * @throws ContractCanNotBeTerminated
     */
    private function validateTerminationDate(string $date): void
    {
        $toDate = new DateTimeImmutable($date);
        $nowDate = new DateTimeImmutable();

        if ($nowDate->diff($toDate)->invert !== 0) {
            throw new ContractCanNotBeTerminated(ResultCodes::CONTRACT_TERMINATION_IN_THE_PAST);
        }
    }
}