#!/usr/bin/php
<?php
/*
 * Name:     mantis.php (UTF-8)
 * Author:   Hauke Schulz <hauke27@googlemail.com>
 * Date:     2013-02-21
 * Language: PHP 5.4
 * IDE:      Netbeans 7.2
 *
 * Description:
 * ------------
 * (de)
 * Post-Commit-Script zum automatisierten kommentieren und schlie�en von
 * Tickets im Mantis-Bugtracker.
 *
 * Es verlässt sich dabei darauf das Systembefehle durch PHP aufgerufen werden
 * dürfen.
 *
 * (en)
 * Post-Commit script to automate the commenting and closing of Tickets
 * in the Mantis-Bugtracker.
 *
 * Example:
 * --------
 *
 * Default PHP behavior
 *
 * php mantis.php /path/to/repo 42
 *
 * or if the file is made executable and renamed to mantis
 *
 * mantis /path/to/repo 42
 *
 * Variables:
 * ----------
 * (de)
 * Werden automatisch von SVN per Hook an das Script übergeben.
 *
 * (en)
 * Are given to the script by the SVN hook.
 *
 * $argv[1] = /path/to/repo // Pfad zum Repository
 * $argv[2] = 123           // Revisionsnummer
 */

/* (de) Einfache �bersetzung
 * (en) Easy translation
 */
define('STR_AUTHOR',   'Autor');
define('STR_REVISION', 'Revision');
define('STR_LOG',      'Log');
define('STR_ADDED',    'Hinzugefügt');
define('STR_MODIFIED', 'Modifiziert');
define('STR_DELETED',  'Gelöscht');

/* (de) Definition des Mantis-Scripts zum Verarbeiten des Commits
 * (en) Definition of the Mantis script to process commit messages
 */
define(
    'MANTIS_CHECKIN_SCRIPT',
    'php -q /home/remind/web/mantis/scripts/checkin.php'
);

/* (de) Optionale TRAC-URL die im Kommentar angezeigt wird.
 * (en) Optional TRAC URL displayed in the comment.
 * @link http://trac.edgewall.org/
 */
//define('STR_TRAC_CHANGESET', 'Änderungen in TRAC ansehen');
//define(
//    'TRAC_CHANGESET_URL',
//    'http://trac.company.com/project/changeset/'
//);

/* (de) Initialisieren des Arrays das alle geänderten Dateien enthält
 * (en) Initialize the array that contains all modified files
 */
$aChanged = array();

/* (de) Initialisieren des Arrays zur Speicherung der Textbausteine
 * (en) Initialize the array that contains all text blocks
 */
$aChangeList = array();

/* (de) Pfad zum Repository auslesen
 * (en) Save the repositorys path
 */
$sRepositoryPath = $argv[1];

/* (de) Revisionsnummer auslesen
 * (en) Save the revision number
 */
$iRevision = (int)$argv[2];

/* (de) Subversion Befehl svnlook date für die Zeitangabe
 * (en) Subversion commant svnlook date to get the timestamp
 */
$sDate = trim(`svnlook date $sRepositoryPath -r $iRevision`);

/* (de) Subversion Befehl svnlook author für den Author
 * (en) Subversion command svnlook author to get the author
 */
$sAuthor = trim(`svnlook author $sRepositoryPath -r $iRevision`);

/* (de) Subversion Befehl svnlook log für den Kommentar
 * (en) Subversion command svnlook log to get the comment
 */
$sComment = trim(`svnlook log $sRepositoryPath -r $iRevision`);

/* (de) Auslesen aller geänderten Dateien
 * (en) Get all changed files
 */
exec("svnlook changed $sRepositoryPath -r $iRevision", $aChanged);

/* (de) Die geänderten Dateien durchlaufen
 * (en) Iterate the changed files
 */
foreach ($aChanged as $sChange) {

    /* (de) Zeichen für die Veränderung an Dateien A,U etc.
     * (en) Save the SVN character for file changes
     */
    $sChangeSymbol   = $sChange[0];

    /* (de) Zeichen für die Ver�nderung an SVN-Properties
     * (en) Save the SVN charactger for property changes
     */
    $sPropertySymbol = $sChange[1];

    /* (de) Rest der Zeile enthält die geänderte Datei
     * (en) The rest of the line contains the file path
     */
    $sLine = substr($sChange, 3);

    /* (de) Wenn an den SVN-Properties etwas geändert wurde
     * (en) If there was an SVN property change
     */
    if ($sPropertySymbol == 'U') {
        $sLine .= ' (props changed)';
    }

    /* (de) Je nach Symbol werden die Änderungen anders zur Ausgabe gespeichert
     * (en) Depending on the character the changes are saved in a diffrent list
     */
    switch ($sChangeSymbol) {
        case 'A': // File added
            $aChangeList['added'][] = $sLine;
            break;

        case '_': // Property modified
        case 'U': // File modified
            $aChangeList['modified'][] = $sLine;
            break;

        case 'D': // File deleted
            $aChangeList['deleted'][] = $sLine;
            break;

        default:
    }
}

/* (de) Initialisierung des Kommentars für Mantis
 * (en) Initialize the comment for mantis
 */
$sMessage = STR_REVISION . ': ' . $iRevision . ' (' . $sDate . ')' . PHP_EOL;
$sMessage .= STR_AUTHOR . ': ' . $sAuthor . PHP_EOL;
$sMessage .= PHP_EOL;

/* (de) Wenn die TRAC-Strings definiert sind
 * (en) If the TRAC strings are defined
 */
if (defined('STR_TRAC_CHANGESET') && defined('TRAC_CHANGESET_URL')) {

    /* (de) Einen Link zum Commit im TRAC einfügen
     * (en) Add a link to the commit made in TRAC project
     */
    $sMessage .= STR_TRAC_CHANGESET . ': ' . TRAC_CHANGESET_URL . $iRevision;
    $sMessage .= PHP_EOL . PHP_EOL;
}

/* (de) Kommentar des Commits ausgeben
 * (en) Display the comment made for the commit
 */
$sMessage .= STR_LOG . ':' . PHP_EOL;
$sMessage .= $sComment . PHP_EOL;

/* (de) Wenn Dateien hinzugefügt wurden
 * (en) If files were added
 */
if (!empty($aChangeList['added'])) {

    /* (de) Den Added-Block hinzuf�gen
     * (en) Add the added block
     */
    $sMessage .= PHP_EOL;
    $sMessage .= STR_ADDED . ':';
    $sMessage .= PHP_EOL;

    /* (de) Alle hinzugefügten Elemente auflisten
     * (en) List all added elements
     */
    foreach ($aChangeList['added'] as $sLine) {
        $sMessage .= '    ' . $sLine . PHP_EOL;
    }

    /* (de) Mit einer Newline den Block abschließen
     * (en) End with a new line
     */
    $sMessage .= PHP_EOL;
}

/* (de) Wenn Dateien modifiziert wurden
 * (en) If files were modified
 */
if (isset($aChangeList['modified'])) {

    /* (de) Den Modifiied-Block hinzufügen
     * (en) Add the modified block
     */
    $sMessage .= PHP_EOL;
    $sMessage .= STR_MODIFIED . ':';
    $sMessage .= PHP_EOL;

    /* (de) Alle modifizierten Elemente auflisten
     * (en) List all modified elements
     */
    foreach ($aChangeList['modified'] as $sLine) {
        $sMessage .= '    ' . $sLine . PHP_EOL;
    }

    /* (de) Mit einer Newline den Block abschließen
     * (en) End with a new line
     */
    $sMessage .= PHP_EOL;
}

/* (de) Wenn Dateien gelöscht wurden
 * (en) If files were deleted
 */
if (isset($aChangeList['deleted'])) {

    /* (de) Den Deleted-Block hinzuf�gen
     * (en) Add the deleted block
     */
    $sMessage .= PHP_EOL;
    $sMessage .= STR_DELETED . ':';
    $sMessage .= PHP_EOL;

    /* (de) Alle hinzugefügten Elemente auflisten
     * (en) List all deleted elements
     */
    foreach ($aChangeList['added'] as $sLine) {
        $sMessage .= '    ' . $sLine . PHP_EOL;
    }
}

/* (de) Ausführen des Mantis-Scripts mit dem generierten Nachricht
 * (en) Execute the Mantis script with the generated message
 */
exec(MANTIS_CHECKIN_SCRIPT . ' <<< "' . $sMessage . '"');

?>
