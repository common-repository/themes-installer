<?php
/*
Plugin Name: Themes Installer
Plugin URI: http://javascripter.altervista.org/themes-installer/
Description: Il tuo host non ti permette di caricare e installare i temi dal tuo computer? Allora questo plugin fa per te! Puoi installare temi per il tuo blog ( formato .zip ) e anche eliminare quelli esistenti.
Tags: themes, installer, altervista
Version: 0.1.2
Author: javascripter
Author URI: http://javascripter.altervista.org
License: GPLv2
*/

// you can't open this file directly so...
if ('themes-installer.php' == basename($_SERVER['SCRIPT_FILENAME'])) {
	die('Please do not access this file directly.');
}

// admin menu
add_action('admin_menu', 'themes_installer');

function themes_installer(){
	if (function_exists('add_options_page')) {
		add_options_page('Installa temi', 'Installa temi', 8, 'themes-installer/themes-installer.php', 'themes_installer_page');
	}
}

function removeDir($dir) {
    $dir .= '/';

    if(!is_dir($dir)) {
        return false;
    }

    $g = glob($dir . '*');

    if(!$g || !count($g)) {
        return false;
    }

    /* htaccess fix */
    $htaccess = $dir . '.htaccess';
    if(!in_array($htaccess, $g) && file_exists($htaccess)) {
        $g[] = $htaccess;
    }

    foreach($g as $f) {
        if(is_dir($f)) {
            removeDir($f);
        } else {
            unlink($f);
        }
    }

    return @rmdir($dir);
}

$j = 0;
$path = '';

function pre_extract($id, $info) {
    global $j, $path;

    if($j === 0) {
        $path = $info['stored_filename'];
        $j = strlen($path);
    }

    return substr($info['stored_filename'], 0, $j) === $path ? 1 : 0;
}

function theme_sw($name) {
    global $wpdb;

    $name = $wpdb->escape($name);
    $table = $wpdb->prefix . 'options';

    return $wpdb->query("UPDATE $table SET option_value = '$name' WHERE option_name IN ('template', 'stylesheet')");
}

function themes_installer_page() {

$f = @$_FILES['f'];
$up = false;
$e = false;
$msg = '';

if(isset($f)) {
    $up = true;
    $name = $f['name'];
    $type = $f['type'];
    $path = $f['tmp_name'];
    $error = $f['error'];
    // $size = $f['size'];

    if($error == UPLOAD_ERR_OK) {
        if(!in_array($type, array('application/zip', 'application/x-zip-compressed', 'application/x-zip'))) {
            $msg = 'Puoi caricare soltanto file zip!';
            $e = true;
        } else {
            require 'pclzip.lib.php';
            $archive = new PclZip($path);

            if($arr = $archive->extract(PCLZIP_OPT_PATH, ABSPATH . 'wp-content/themes/', PCLZIP_CB_PRE_EXTRACT, 'pre_extract')) {
                $sw = isset($_POST['switch']);
                $dn = substr($arr[0]['stored_filename'], 0, -1);

                $msg = 'Tema ' . $dn . ' installato con successo' . ($sw ? '. ' : ' ora puoi andare ad <a href="./themes.php">attivarlo</a> ( di solito il tema lo trovi all\'ultima pagina, ma non &egrave; certo )');

                if($sw) {
                    $msg .= theme_sw($dn) ? 'Il tema &egrave; stato attivato' : 'Il tema non &egrave; stato attivato a causa di un\'errore della query';
                }
            } else {
                $e = true;
                $msg = 'Archivio non compatibile ( ' . $archive->errorInfo(true). ' )';
            }
        }
    } else {
        $e = true;
        $msg = 'Devi scegliere un tema da installare';
    }
} else if(isset($_POST['dt'])) {
    $up = true;
    $themes = is_array(@$_POST['del']) ? $_POST['del'] : array();

    if(count($themes) === 0) {
        $e = true;
        $msg = 'Seleziona dei temi da cancellare';
    } else {
        $msg = 'Temi selezionati cancellati con successo!';
        foreach($themes as $theme) {
            if(!removeDir(ABSPATH . 'wp-content/themes/' . $theme)) {
                $e = false;
                $msg = 'Errore';
                break;
            }
        }
    }
} else if(isset($_GET['switch'])) {
    $up = true;

    if(theme_sw($_GET['switch'])) {
        $msg = 'Tema applicato con successo';
    } else {
        $e = true;
        $msg = 'Errore';
    }   
}

$themes = glob(ABSPATH . 'wp-content/themes/*', GLOB_ONLYDIR);

if(!is_array($themes)) {
    $themes = array();
}

$table = '';

foreach($themes as $i => $theme) {
    $path = $theme . '/screenshot.png';
    $img = file_exists($path) ? str_replace(ABSPATH, '', $path) : 'wp-content/plugins/themes-installer/noimg.png';
    $name = 'del[' . $i . ']';
    $id = 'del-' . $i;
    $t = str_replace(ABSPATH . 'wp-content/themes/', '', $theme);

    $table .= '                <tr>
                    <th scope="row"><label for="' . $id . '" title="' . $t . '"><div style="height: 30px"><img src="/' . $img . '" height="30" width="30" style="border: 1px solid black" /><span style="padding-left: 5px; vertical-align: top">' . $t . '</span></div></label></th>
                    <td><input type="checkbox" name="' . $name . '" id="' . $id . '" value="' . $t . '" /> | <a href="options-general.php?page=themes-installer/themes-installer.php&amp;switch=' . $t . '">Applica tema</a></td>
        </tr>
';
}

if($up) {
    echo '<div class="' . ($e === false ? 'updated' : 'error') . ' fade"><p>' . $msg . '</p></div>';
}

echo <<<html
<div class="wrap">
    <div id="icon-options-general" class="icon32"><br></div>
    <h2>Carica un tema</h2>
    <p>Installa <b>SOLAMENTE</b> temi provenienti da fonti sicure, non mi assumo nessuna responsabilit&agrave; se carichi contenuti dannosi nel blog.</p>
    <form action="options-general.php?page=themes-installer/themes-installer.php" method="POST" enctype="multipart/form-data">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="up-file">Seleziona il tema da installare:</label></th>
                <td><input type="file" name="f" id="up-file" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="switch">Applica il tema appena al termine dell'upload</label></th>
                <td><input type="checkbox" name="switch" id="switch" /></td>
            </tr>
            <tr>
                <td></td>
                <td><input type="submit" value="Installa il tema" class="button-primary" /></td>
            </tr>
        </table>
    </form>
    <h2>Lista dei temi installati</h2>
        <p>Puoi selezionare i temi che desideri eliminare e premere il pulsante "Elimina i temi selezionati" in fondo alla pagina oppure puoi applicare uno dei temi in lista</p>
        <form action="options-general.php?page=themes-installer/themes-installer.php" method="POST">
            <input type="hidden" name="dt" value="1" />
            <table class="form-table">
            $table
            <tr>
                <td></td>
                <td><input type="submit" value="Elimina i temi selezionati" class="button-primary" /></td>
            </tr>
        </table>
    </form>
</div>
html;

}
?>