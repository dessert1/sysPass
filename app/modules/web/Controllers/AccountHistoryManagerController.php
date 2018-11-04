<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Modules\Web\Controllers;

use SP\Core\Acl\Acl;
use SP\Core\Events\Event;
use SP\Core\Events\EventMessage;
use SP\Http\JsonResponse;
use SP\Modules\Web\Controllers\Helpers\Grid\AccountHistoryGrid;
use SP\Modules\Web\Controllers\Traits\ItemTrait;
use SP\Modules\Web\Controllers\Traits\JsonTrait;
use SP\Services\Account\AccountHistoryService;
use SP\Services\Account\AccountService;

/**
 * Class AccountHistoryManagerController
 *
 * @package SP\Modules\Web\Controllers
 */
final class AccountHistoryManagerController extends ControllerBase
{
    use JsonTrait, ItemTrait;

    /**
     * @var AccountHistoryService
     */
    protected $accountHistoryService;

    /**
     * @return bool
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     * @throws \SP\Core\Exceptions\SPException
     */
    public function searchAction()
    {
        $this->checkSecurityToken($this->previousSk, $this->request);

        if (!$this->acl->checkUserAccess(Acl::ACCOUNTMGR_HISTORY_SEARCH)) {
            return $this->returnJsonResponse(JsonResponse::JSON_ERROR, __u('You don\'t have permission to do this operation'));
        }

        $this->view->addTemplate('datagrid-table', 'grid');
        $this->view->assign('index', $this->request->analyzeInt('activetab', 0));
        $this->view->assign('data', $this->getSearchGrid());

        return $this->returnJsonResponseData(['html' => $this->render()]);
    }

    /**
     * getSearchGrid
     *
     * @return $this
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    protected function getSearchGrid()
    {
        $itemSearchData = $this->getSearchData($this->configData->getAccountCount(), $this->request);

        $historyGrid = $this->dic->get(AccountHistoryGrid::class);

        return $historyGrid->updatePager($historyGrid->getGrid($this->accountHistoryService->search($itemSearchData)), $itemSearchData);
    }

    /**
     * Delete action
     *
     * @param $id
     *
     * @return bool
     */
    public function deleteAction($id = null)
    {
        try {
            $this->checkSecurityToken($this->previousSk, $this->request);

            if ($id === null) {
                $this->accountHistoryService->deleteByIdBatch($this->getItemsIdFromRequest($this->request));

                $this->eventDispatcher->notifyEvent('delete.accountHistory.selection',
                    new Event($this, EventMessage::factory()->addDescription(__u('Accounts removed')))
                );

                return $this->returnJsonResponse(JsonResponse::JSON_SUCCESS, __u('Accounts removed'));
            }
            $accountDetails = $this->accountHistoryService->getById($id);

            $this->accountHistoryService->delete($id);

            $this->eventDispatcher->notifyEvent('delete.accountHistory',
                new Event($this, EventMessage::factory()
                    ->addDescription(__u('Account removed'))
                    ->addDetail(__u('Account'), $accountDetails->getName())
                    ->addDetail(__u('Client'), $accountDetails->getClientName()))
            );

            return $this->returnJsonResponse(JsonResponse::JSON_SUCCESS, __u('Account removed'));
        } catch (\Exception $e) {
            processException($e);

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Saves restore action
     *
     * @param int $id Account's history ID
     *
     * @return bool
     */
    public function restoreAction($id)
    {
        try {
            $this->checkSecurityToken($this->previousSk, $this->request);

            $accountDetails = $this->accountHistoryService->getById($id);

            $accountService = $this->dic->get(AccountService::class);

            if ($accountDetails->isModify) {
                $accountService->editRestore($id, $accountDetails->getAccountId());
            } else {
                $accountService->createFromHistory($accountDetails);
            }

            $this->eventDispatcher->notifyEvent('restore.accountHistory',
                new Event($this, EventMessage::factory()
                    ->addDescription(__u('Account restored'))
                    ->addDetail(__u('Account'), $accountDetails->getName())
                    ->addDetail(__u('Client'), $accountDetails->getClientName()))
            );

            return $this->returnJsonResponse(JsonResponse::JSON_SUCCESS, __u('Account restored'));
        } catch (\Exception $e) {
            processException($e);

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Initialize class
     *
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \SP\Services\Auth\AuthException
     */
    protected function initialize()
    {
        $this->checkLoggedIn();

        $this->accountHistoryService = $this->dic->get(AccountHistoryService::class);
    }
}