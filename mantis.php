#!/usr/bin/php
<?php
/*
 * Name:     mantis.php (UTF-8)
 * Author:   Hauke Schulz <hauke27@googlemail.com>
 * Date:     2013-02-21
 * Language: PHP 5.4
 * IDE:      Netbeans 7.2
 *
 * License: http://www.opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 *
 *
 * Description:
 * ------------
 * Post-Commit script to automate the commenting and closing of Tickets
 * in the Mantis-Bugtracker.
 *
 * Example:
 * --------
 *
 * Default PHP behavior
 *
 * >> php mantis.php /path/to/repo 42
 *
 * or if the file is made executable and renamed to mantis
 *
 * >> mantis /path/to/repo 42
 *
 * Variables:
 * ----------
 * Are given to the script by the SVN hook.
 *
 * $argv[1] = /path/to/repo // Pfad zum Repository
 * $argv[2] = 123           // Revisionsnummer
 */

/* Easy translation */
define('STR_AUTHOR',   'Autor');
define('STR_REVISION', 'Revision');
define('STR_LOG',      'Log');
define('STR_ADDED',    'Hinzugefügt');
define('STR_MODIFIED', 'Modifiziert');
define('STR_DELETED',  'Gelöscht');

/* Definition of the Mantis script to process commit messages */
define(
    'MANTIS_CHECKIN_SCRIPT',
    'php -q /var/www/web/mantis/scripts/checkin.php'
);

/* Optional TRAC URL displayed in the comment.
 * @link http://trac.edgewall.org/
 */
//define('STR_TRAC_CHANGESET', 'Änderungen in TRAC ansehen');
//define(
//    'TRAC_CHANGESET_URL',
//    'http://trac.company.com/project/changeset/'
//);

/* Initialize the array that contains all modified files */
$changed = array();

/* Initialize the array that contains all text blocks */
$changeList = array();

/* Save the repositorys path */
$repositoryPath = $argv[1];

/* Save the revision number */
$revision = (int)$argv[2];

/* Subversion commant svnlook date to get the timestamp */
$date = trim(`svnlook date $repositoryPath -r $revision`);

/* Subversion command svnlook author to get the author */
$author = trim(`svnlook author $repositoryPath -r $revision`);

/* Subversion command svnlook log to get the comment */
$comment = trim(`svnlook log $repositoryPath -r $revision`);

/* Get all changed files */
exec("svnlook changed $repositoryPath -r $revision", $changed);

/* Iterate the changed files */
foreach ($changed as $sChange) {

    /* Save the SVN character for file changes */
    $changeSymbol   = $sChange[0];

    /* Save the SVN charactger for property changes */
    $propertySymbol = $sChange[1];

    /* The rest of the line contains the file path */
    $line = substr($sChange, 3);

    /* If there was an SVN property change */
    if ($propertySymbol == 'U') {
        $line .= ' (props changed)';
    }

    /* Depending on the character the changes are saved in a diffrent list */
    switch ($changeSymbol) {
        case 'A': // File added
            $changeList['added'][] = $line;
            break;

        case '_': // Property modified
        case 'U': // File modified
            $changeList['modified'][] = $line;
            break;

        case 'D': // File deleted
            $changeList['deleted'][] = $line;
            break;

        default:
    }
}

/* Initialize the comment for mantis */
$message  = STR_REVISION . ': ' . $revision . ' (' . $date . ')' . PHP_EOL;
$message .= STR_AUTHOR . ': ' . $author . PHP_EOL;
$message .= PHP_EOL;

/* If the TRAC strings are defined */
if (defined('STR_TRAC_CHANGESET') && defined('TRAC_CHANGESET_URL')) {

    /* Add a link to the commit made in TRAC project */
    $message .= STR_TRAC_CHANGESET . ': ' . TRAC_CHANGESET_URL . $revision;
    $message .= PHP_EOL . PHP_EOL;
}

/* Display the comment made for the commit */
$message .= STR_LOG . ':' . PHP_EOL;
$message .= $comment . PHP_EOL;

/* If files were added */
if (!empty($changeList['added'])) {

    /* Add the added block */
    $message .= PHP_EOL;
    $message .= STR_ADDED . ':';
    $message .= PHP_EOL;

    /* List all added elements */
    foreach ($changeList['added'] as $line) {
        $message .= '    ' . $line . PHP_EOL;
    }

    /* End with a new line */
    $message .= PHP_EOL;
}

/* If files were modified */
if (isset($changeList['modified'])) {

    /* Add the modified block */
    $message .= PHP_EOL;
    $message .= STR_MODIFIED . ':';
    $message .= PHP_EOL;

    /* List all modified elements */
    foreach ($changeList['modified'] as $line) {
        $message .= '    ' . $line . PHP_EOL;
    }

    /* End with a new line */
    $message .= PHP_EOL;
}

/* If files were deleted */
if (isset($changeList['deleted'])) {

    /* Add the deleted block */
    $message .= PHP_EOL;
    $message .= STR_DELETED . ':';
    $message .= PHP_EOL;

    /* List all deleted elements */
    foreach ($changeList['added'] as $line) {
        $message .= '    ' . $line . PHP_EOL;
    }
}

/* Execute the Mantis script with the generated message */
exec(MANTIS_CHECKIN_SCRIPT . ' <<< "' . $message . '"');
