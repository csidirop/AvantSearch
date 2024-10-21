<?php

class AvantSearch_Controller_Plugin_DispatchFilter extends Zend_Controller_Plugin_Abstract
{
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        $isAdminRequest = $request->getParam('admin', false);
        if ($isAdminRequest)
            return;

        $moduleName = $request->getModuleName();
        $controllerName = $request->getControllerName();
        if ($request->getParam('collection', false)) return; // Do not use on collections
        $actionName = $request->getActionName();
        $this->bypassOmekaSearch($request, $moduleName, $controllerName, $actionName);
    }

    protected function bypassOmekaSearch($request, $moduleName, $controllerName, $actionName)
    {
        $isSearchRequest = $moduleName == 'default' && $controllerName == 'search' && $actionName == 'index';
        $isBrowseRequest = $moduleName == 'default' && $controllerName == 'items' && ($actionName == 'browse' || $actionName == 'search');

        if ($isSearchRequest || $isBrowseRequest)
        {
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoUrl(WEB_ROOT . '/find');
        }
    }
}
