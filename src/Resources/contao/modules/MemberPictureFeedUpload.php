<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 04.10.2018
 * Time: 11:56
 */

namespace Markocupic\MemberPictureFeedBundle\Contao\Modules;

use Contao\Module;
use Contao\BackendTemplate;
use Contao\FrontendUser;
use Patchwork\Utf8;


class MemberPictureFeedUpload extends Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_memberPictureFeedUpload';

    /**
     * @var
     */
    protected $objUser;


    /**
     * Display a wildcard in the back end
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['memberPictureFeedUpload'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        if (FE_USER_LOGGED_IN)
        {
            $this->objUser = FrontendUser::getInstance();
        }
        else
        {
            return '';
        }


        return parent::generate();
    }


    /**
     * Generate the module
     */
    protected function compile()
    {

    }


    /**
     * @return null
     */
    protected function generateFirstForm()
    {

        $objForm = new Form('form-activate-member-account', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });
        $url = Environment::get('uri');
        $objForm->setFormActionFromUri($url);


        $objForm->addFormField('username', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_sacMemberId'],
            'inputType' => 'text',
            'eval'      => array('mandatory' => true),
        ));
        $objForm->addFormField('email', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_email'],
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'rgxp' => 'email'),
        ));
        $objForm->addFormField('dateOfBirth', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_dateOfBirth'],
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'rgxp' => 'date', 'datepicker' => true),
        ));
        $objForm->addFormField('agb', array(
            'label'     => array('', sprintf($GLOBALS['TL_LANG']['MSC']['activateMemberAccount_agb'], '<a href="#" data-toggle="modal" data-target="#agbModal">', '</a>')),
            'inputType' => 'checkbox',
            'eval'      => array('mandatory' => true),
        ));
        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_startActivationProcess'],
            'inputType' => 'submit',
        ));

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();


        $objWidget = $objForm->getWidget('dateOfBirth');
        $objWidget->addAttribute('placeholder', 'dd.mm.YYYY');


        // validate() also checks whether the form has been submitted
        if ($objForm->validate())
        {
            $hasError = false;

            // Validate sacMemberId
            $objMember = Database::getInstance()->prepare('SELECT * FROM tl_member WHERE sacMemberId=?')->limit(1)->execute(Input::post('username'));
            if (!$objMember->numRows)
            {
                $this->Template->errorMsg = sprintf($GLOBALS['TL_LANG']['ERR']['activateMemberAccount_couldNotAssignUserToSacMemberId'], Input::post('username'));
                $hasError = true;
            }

            if (!$hasError)
            {
                if (Date::parse(Config::get('dateFormat'), $objMember->dateOfBirth) !== Input::post('dateOfBirth'))
                {
                    $this->Template->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_sacMemberIdAndDateOfBirthDoNotMatch'];
                    $hasError = true;
                }
            }

            if (!$hasError)
            {
                if (strtolower(Input::post('email')) !== strtolower($objMember->email))
                {
                    $this->Template->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_sacMemberIdAndEmailDoNotMatch'];
                    $hasError = true;
                }
            }

            if (!$hasError)
            {
                if ($objMember->login)
                {
                    $this->Template->errorMsg = sprintf($GLOBALS['TL_LANG']['ERR']['activateMemberAccount_accountWithThisSacMemberIdIsAllreadyRegistered'], Input::post('username'));
                    $hasError = true;
                }
            }

            if (!$hasError)
            {
                if ($objMember->disable)
                {
                    $this->Template->errorMsg = sprintf($GLOBALS['TL_LANG']['ERR']['activateMemberAccount_accountWithThisSacMemberIdHasBeendDeactivatedAndIsNoMoreValid'], Input::post('username'));
                    $hasError = true;
                }
            }


            $this->Template->hasError = $hasError;


            // Save data to tl_member
            if (!$hasError)
            {
                $objMemberModel = MemberModel::findByPk($objMember->id);
                if ($objMemberModel !== null)
                {
                    $token = rand(111111, 999999);
                    $objMemberModel->activation = $token;
                    $objMemberModel->activationLinkLifetime = time() + $this->activationLinkLifetime;
                    $objMemberModel->activationFalseTokenCounter = 0;
                    $objMemberModel->save();

                    if ($this->notifyMember($objMemberModel))
                    {
                        // Set session dataR
                        $_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId'] = $objMemberModel->id;
                        $_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] = 2;

                        // Redirect
                        $url = Url::removeQueryString(['step']);
                        $url = Url::addQueryString('step=2', $url);
                        Controller::redirect($url);
                    }
                    else
                    {
                        $hasError = true;
                        $this->Template->hasError = $hasError;
                        $this->Template->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_couldNotTerminateActivationProcess'];
                    }
                }
            }
        }

        $this->objForm = $objForm;
    }

    /**
     *
     */
    protected function generateSecondForm()
    {
        $objMember = MemberModel::findByPk($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
        if ($objMember === null)
        {
            $url = Url::removeQueryString(['step']);
            Controller::redirect($url);
        }
        $objForm = new Form('form-activate-member-account-activation-token', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri(Environment::get('uri'));

        // Password
        $objForm->addFormField('activationToken', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_pleaseEnterTheActivationCode'],
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'minlength' => 6, 'maxlength' => 6),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_proceedActivationProcess'],
            'inputType' => 'submit',
        ));

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();


        // Check activation token
        $hasError = false;

        // validate() also checks whether the form has been submitted
        if ($objForm->validate() && Input::post('activationToken') !== '')
        {

            $token = trim(Input::post('activationToken'));

            $objMember = MemberModel::findByPk($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
            if ($objMember === null)
            {
                $hasError = true;
                $url = Url::removeQueryString(['step']);
                $this->Template->doNotShowForm = true;
                $this->Template->errorMsg = sprintf('Leider ist die Session abgelaufen. Starte den Aktivierungsprozess von vorne.<br><a href="%s">Aktivierungsprozess neu starten</a>', $url);
            }

            if ($objMember->disable)
            {
                $hasError = true;
                $this->Template->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_accountActivationStoppedAccountIsDeactivated'];
            }

            $objDb = Database::getInstance()->prepare('SELECT * FROM tl_member WHERE id=? AND activation=?')->limit(1)->execute($objMember->id, $token);

            if (!$hasError && !$objDb->numRows)
            {
                $hasError = true;
                $objMember->activationFalseTokenCounter++;
                $objMember->save();
                // Too many tries
                if ($objMember->activationFalseTokenCounter > 5)
                {
                    $objMember->activationFalseTokenCounter = 0;
                    $objMember->activation = '';
                    $objMember->activationLinkLifetime = 0;
                    $objMember->save();
                    unset($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
                    $url = Url::removeQueryString(['step']);
                    $this->Template->doNotShowForm = true;
                    $this->Template->errorMsg = sprintf($GLOBALS['TL_LANG']['ERR']['activateMemberAccount_accountActivationStoppedInvalidActivationCodeAndTooMuchTries'], '<br><a href="' . $url . '">', '</a>');
                }
                else
                {
                    // False token
                    $this->Template->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_invalidActivationCode'];
                }
            }
            else
            {
                // Token has expired
                if ($objDb->activationLinkLifetime < time())
                {
                    $hasError = true;
                    $this->Template->doNotShowForm = true;
                    $this->Template->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_activationCodeExpired'];
                }
                else
                {
                    // All ok!
                    $objMember->activationFalseTokenCounter = 0;
                    $objMember->activation = '';
                    $objMember->activationLinkLifetime = 0;

                    // Set session data
                    $_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] = 3;

                    // Redirect
                    $url = Url::removeQueryString(['step']);
                    $url = Url::addQueryString('step=3', $url);
                    Controller::redirect($url);
                }
            }
        }

        $this->Template->hasError = $hasError;
        $this->objForm = $objForm;
    }


    /**
     *
     */
    protected function generateThirdForm()
    {

        $objForm = new Form('form-activate-member-account-set-password', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri(Environment::get('uri'));

        // Password
        $objForm->addFormField('password', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_pleaseEnterPassword'],
            'inputType' => 'password',
            'eval'      => array('mandatory' => true, 'maxlength' => 255),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_activateMemberAccount'],
            'inputType' => 'submit',
        ));

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();


        // Check activation token
        $hasError = false;

        // Validate session
        $objMemberModel = MemberModel::findByPk($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
        if ($objMemberModel === null)
        {
            $this->Template->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_sessionExpired'];
            $hasError = true;
        }

        $this->Template->hasError = $hasError;


        // validate() also checks whether the form has been submitted
        if ($objForm->validate() && $hasError === false)
        {

            // Save data to tl_member
            if (!$hasError)
            {
                if ($objMemberModel !== null)
                {
                    $objMemberModel->password = password_hash(Input::post('password'), PASSWORD_DEFAULT);
                    $objMemberModel->activation = '';
                    $objMemberModel->activationLinkLifetime = 0;
                    $objMember->activationFalseTokenCounter = 0;
                    $objMemberModel->login = '1';

                    // Add groups
                    $arrGroups = StringUtil::deserialize($objMemberModel->groups, true);
                    $arrGroups = array_merge($arrGroups, $this->arrGroups);
                    $arrGroups = array_unique($arrGroups);
                    $arrGroups = array_filter($arrGroups);
                    $objMemberModel->groups = serialize($arrGroups);
                    $objMemberModel->save();

                    // Set sesion data
                    $_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] = 4;

                    // Redirect
                    $url = Url::removeQueryString(['step']);
                    $url = Url::addQueryString('step=4', $url);
                    Controller::redirect($url);
                }
            }
        }

        $this->objForm = $objForm;
    }


    /**
     * @param $objMember
     * @return bool
     */
    private function notifyMember($objMember)
    {
        // Use terminal42/notification_center
        if ($this->objNotification !== null)
        {
            // Set token array
            $arrTokens = array(
                'firstname'   => html_entity_decode($objMember->firstname),
                'lastname'    => html_entity_decode($objMember->lastname),
                'street'      => html_entity_decode($objMember->street),
                'postal'      => html_entity_decode($objMember->postal),
                'city'        => html_entity_decode($objMember->city),
                'phone'       => html_entity_decode($objMember->phone),
                'activation'  => $objMember->activation,
                'username'    => html_entity_decode($objMember->username),
                'sacMemberId' => html_entity_decode($objMember->username),
                'email'       => $objMember->email,
            );

            $this->objNotification->send($arrTokens, 'de');

            return true;
        }
        return false;
    }

}
