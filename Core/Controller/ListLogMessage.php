<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2017-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Core\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Dinamic\Model\CronJob;

/**
 * Controller to list the items in the LogMessage model
 *
 * @author Carlos García Gómez          <carlos@facturascripts.com>
 * @author Francesc Pineda Segarra      <francesc.pineda.segarra@gmail.com>
 * @author Cristo M. Estévez Hernández  <cristom.estevez@gmail.com>
 */
class ListLogMessage extends ListController
{

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData()
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'logs';
        $data['icon'] = 'fas fa-file-medical-alt';
        return $data;
    }

    /**
     * Load views
     */
    protected function createViews()
    {
        $this->createLogMessageView();
        $this->createCronJobView();
    }

    /**
     * Create view to view all information about crons.
     *
     * @param string $viewName
     */
    protected function createCronJobView(string $viewName = 'ListCronJob')
    {
        $this->addView($viewName, 'CronJob', 'crons', 'fas fa-cogs');
        $this->addSearchFields($viewName, ['jobname', 'pluginname']);
        $this->addOrderBy($viewName, ['jobname'], 'job-name');
        $this->addOrderBy($viewName, ['pluginname'], 'plugin');
        $this->addOrderBy($viewName, ['date'], 'date');
        $this->addOrderBy($viewName, ['duration'], 'duration');

        // filters
        $plugins = $this->codeModel->all('cronjobs', 'pluginname', 'pluginname');
        $this->addFilterSelect($viewName, 'pluginname', 'plugin', 'pluginname', $plugins);

        $this->addFilterPeriod($viewName, 'date', 'period', 'date');

        // settings
        $this->setSettings($viewName, 'btnNew', false);
    }

    /**
     * Create view to get information about all logs.
     *
     * @param string $viewName
     */
    protected function createLogMessageView(string $viewName = 'ListLogMessage')
    {
        $this->addView($viewName, 'LogMessage', 'history', 'fas fa-history');
        $this->addSearchFields($viewName, ['context', 'message', 'uri']);
        $this->addOrderBy($viewName, ['time', 'id'], 'date', 2);
        $this->addOrderBy($viewName, ['level'], 'level');
        $this->addOrderBy($viewName, ['ip'], 'ip');

        // filters
        $channels = $this->codeModel->all('logs', 'channel', 'channel');
        $this->addFilterSelect($viewName, 'channel', 'channel', 'channel', $channels);

        $levels = $this->codeModel->all('logs', 'level', 'level');
        $this->addFilterSelect($viewName, 'level', 'level', 'level', $levels);

        $this->addFilterAutocomplete($viewName, 'nick', 'user', 'nick', 'users');
        $this->addFilterAutocomplete($viewName, 'ip', 'ip', 'ip', 'logs');

        $uris = $this->codeModel->all('logs', 'uri', 'uri');
        $this->addFilterSelect($viewName, 'url', 'url', 'uri', $uris);

        $models = $this->codeModel->all('logs', 'model', 'model');
        $this->addFilterSelect($viewName, 'model', 'doc-type', 'model', $models);

        $this->addFilterPeriod($viewName, 'time', 'period', 'time');

        // settings
        $this->setSettings($viewName, 'btnNew', false);
    }

    protected function enableCronJob(): bool
    {
        if (false === $this->validateFormToken()) {
            return true;
        }

        if (false === $this->user->can('EditCronJob', 'update')) {
            $this->toolBox()->i18nLog()->warning('not-allowed-modify');
            return true;
        }

        $codes = $this->request->request->get('code', []);
        if (false === is_array($codes)) {
            return true;
        }

        foreach ($codes as $code) {
            $cron = new CronJob();
            if (false === $cron->loadFromCode($code)) {
                continue;
            }

            $cron->enabled = true;
            if (false === $cron->save()) {
                $this->toolBox()->i18nLog()->warning('record-save-error');
                return true;
            }
        }

        $this->toolBox()->i18nLog()->notice('record-updated-correctly');
        return true;
    }

    protected function disableCronJob(): bool
    {
        if (false === $this->validateFormToken()) {
            return true;
        }

        if (false === $this->user->can('EditCronJob', 'update')) {
            $this->toolBox()->i18nLog()->warning('not-allowed-modify');
            return true;
        }

        $codes = $this->request->request->get('code', []);
        if (false === is_array($codes)) {
            return true;
        }

        foreach ($codes as $code) {
            $cron = new CronJob();
            if (false === $cron->loadFromCode($code)) {
                continue;
            }

            $cron->enabled = false;
            if (false === $cron->save()) {
                $this->toolBox()->i18nLog()->warning('record-save-error');
                return true;
            }
        }

        $this->toolBox()->i18nLog()->notice('record-updated-correctly');
        return true;
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'enable-cronjob':
                $this->enableCronJob();
                break;

            case 'disable-cronjob':
                $this->disableCronJob();
                break;

            default:
                parent::execPreviousAction($action);
        }
    }

    protected function loadBtnCronjob(string $viewName)
    {
        $this->addButton($viewName, [
            'action' => 'enable-cronjob',
            'color' => 'success',
            'icon' => 'fas fa-eye',
            'label' => $this->toolBox()->i18n()->trans('enable'),
            'type' => 'action'
        ]);

        $this->addButton($viewName, [
            'action' => 'disable-cronjob',
            'color' => 'warning',
            'icon' => 'fas fa-eye-slash',
            'label' => $this->toolBox()->i18n()->trans('disable'),
            'type' => 'action'
        ]);
    }

    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'ListCronJob':
                parent::loadData($viewName, $view);
                if ($view->model->count() > 0) {
                    $this->loadBtnCronjob($viewName);
                }
                break;

            default:
                parent::loadData($viewName, $view);
        }
    }
}
