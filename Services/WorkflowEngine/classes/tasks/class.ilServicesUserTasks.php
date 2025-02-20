<?php

/* Copyright (c) 1998-2016 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilServicesUserTasks
 *
 * @author Maximilian Becker <mbecker@databay.de>
 * @version $Id$
 *
 */
class ilServicesUserTasks
{
    private const ANON_FIRSTNAME = 'Anonymous';
    private const ANON_LASTNAME = 'Exam-User';
    private const ANON_LOGIN_PREFIX = 'EX-';
    private const ANON_GENDER = 'm';
    private const PASSWORD_CHARACTERSET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';

    /**
     * @param ilNode $context
     * @param array  $params
     * @return array
     */
    public static function createAnonymousUsers(ilNode $context, array $params): array
    {
        $input_params = $params[0];
        $output_params = $params[1];

        // IN: useridlist
        // OUT: anonaccountlist, userIdList

        $discloseMap = [];

        foreach ($input_params['usrIdList'] as $user_id) {
            $source_user = new ilObjUser($user_id, false);
            $anon_login = self::getValidLogin();
            $anon_password = self::generatePassword();
            $new_id = self::createUser(
                $anon_login,
                $anon_password,
                $source_user->getEmail()
            );

            $discloseMap[] = [
                'Original User' => $user_id,
                'Original Login' => $source_user->getLogin(),
                'Original Firstname' => $source_user->getFirstname(),
                'Original Lastname' => $source_user->getLastname(),
                'Original Matriculation' => $source_user->getMatriculation(),
                'Original Gender' => $source_user->getGender(),
                'Original EMail' => $source_user->getEmail(),
                'Anon User' => $new_id,
                'Anon Login' => $anon_login,
                'Anon Password' => $anon_password
            ];
        }

        return [$output_params[0] => $discloseMap];
    }

    /**
     * @return string
     */
    protected static function getValidLogin(): string
    {
        $random = new ilRandom();
        do {
            $login = self::ANON_LOGIN_PREFIX . str_pad($random->int(0, 9999999), 7, STR_PAD_LEFT);
        } while (ilObjUser::_loginExists($login));

        return $login;
    }

    /**
     * @param int $length
     * @return string
     */
    protected static function generatePassword(int $length = 8): string
    {
        $random = new ilRandom();
        $password = [];
        $setLength = strlen(self::PASSWORD_CHARACTERSET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $index = $random->int(0, $setLength);
            $password[] = self::PASSWORD_CHARACTERSET[$index];
        }

        return implode($password);
    }

    /**
     * @param string $login
     * @param string $password
     * @param string $email
     * @return int
     */
    protected static function createUser(string $login, string $password, string $email): int
    {
        global $DIC;
        $rbacadmin = $DIC['rbacadmin'];

        $user = new ilObjUser();
        $user->setTimeLimitUnlimited(true);
        $user->setFirstname(self::ANON_FIRSTNAME);
        $user->setLastname(self::ANON_LASTNAME);
        $user->setEmail($email);
        $user->setGender(self::ANON_GENDER);
        $user->setPasswd($password, ilObjUser::PASSWD_PLAIN);
        $user->setLogin($login);
        $user->setActive(true, 6);
        $user->create();

        $user->setLastPasswordChangeTS(0);
        $user->saveAsNew();

        $user->setPref('send_info_mails', 'n');
        $user->writePrefs();

        $rbacadmin->assignUser(4, $user->getId(), true);

        return $user->getId();
    }

    /**
     * @param ilNode $context
     * @param array  $params
     */
    public static function repersonalizeUsers(ilNode $context, array $params): void
    {
        // IN: discloseMap
        $input_params = $params[0];
        $output_params = $params[1];

        foreach ($input_params['discloseMap'] as $disclose_entry) {
            $anon_user = new ilObjUser($disclose_entry['Anon User'], false);
            $anon_user->setFirstname($disclose_entry['Original Firstname']);
            $anon_user->setLastname($disclose_entry['Original Lastname']);
            $anon_user->setMatriculation($disclose_entry['Original Matriculation']);
            $anon_user->setGender($disclose_entry['Original Gender']);
            $anon_user->update();
        }

        // OUT: void
    }
}
