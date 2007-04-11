<?php

/**
 * Image class that builds off the File_Command class
 * Assists with image files
 *
 * @author Matthew McNaney <matt at tux dot appstate dot edu>
 * @version $Id$
 */

PHPWS_Core::requireConfig('filecabinet');
PHPWS_Core::initModClass('filecabinet', 'File_Common.php');


class PHPWS_Image extends File_Common {
    var $width            = NULL;
    var $height           = NULL;
    var $alt              = NULL;
    var $border           = 0;
    var $_classtype       = 'image';
    var $_max_width       = 0;
    var $_max_height      = 0;

    function PHPWS_Image($id=NULL)
    {
        $this->loadAllowedTypes();
        $this->setMaxWidth(PHPWS_Settings::get('filecabinet', 'max_image_width'));
        $this->setMaxHeight(PHPWS_Settings::get('filecabinet', 'max_image_height'));
        $this->setMaxSize(PHPWS_Settings::get('filecabinet', 'max_image_size'));

        if (empty($id)) {
            return;
        }
    
        $this->id = (int)$id;
        $result = $this->init();
        if (PEAR::isError($result)) {
            $this->id = 0;
            $this->_errors[] = $result;
        } elseif (empty($result)) {
            $this->id = 0;
            $this->_errors[] = PHPWS_Error::get(FC_IMG_NOT_FOUND, 'filecabinet', 'PHPWS_Image');
        }
    }

    function init()
    {
        if (empty($this->id)) {
            return false;
        }

        $db = new PHPWS_DB('images');
        return $db->loadObject($this);
    }

    function loadDimensions()
    {
        if (empty($this->file_directory) ||
            empty($this->file_name) ||
            !is_file($this->getPath())) {
            return false;
        }

        $dimen = getimagesize($this->getPath());
        if (!is_array($dimen)) {
            return false;
        }
        $this->width  = $dimen[0];
        $this->height = $dimen[1];
        if (empty($this->size)) {
            $this->loadFileSize();
        }
        return true;
    }


    function allowDimensions()
    {
        if ($this->allowWidth() && $this->allowHeight()) {
            return true;
        } else {
            return false;
        }
    }


    function allowHeight($imageheight=NULL)
    {
        if (!isset($imageheight)) {
            $imageheight = &$this->height;
        }

        return ($imageheight <= $this->_max_height) ? TRUE : FALSE;
    }


    function allowWidth($imagewidth=NULL)
    {
        if (!isset($imagewidth)) {
            $imagewidth = &$this->width;
        }

        return ($imagewidth <= $this->_max_width) ? TRUE : FALSE;
    }

    function popupSize()
    {
        $padded_width = $this->width + 25;
        $padded_height = $this->height + 100;

        if (!empty($this->description)) {
            $padded_height += strlen($this->description) * 3;
        }

        if ( $padded_width > FC_MAX_IMAGE_POPUP_WIDTH || $padded_height > FC_MAX_IMAGE_POPUP_HEIGHT ) {
            return array(FC_MAX_IMAGE_POPUP_WIDTH, FC_MAX_IMAGE_POPUP_HEIGHT);
        }

        $final_width = $final_height = 0;

        for ($lmt = 200; $lmt += 50; $lmt < 1300) {
            if (!$final_width && $padded_width < $lmt) {
                $final_width = $lmt;
            }

            if (!$final_height && $padded_height < $lmt ) {
                $final_height = $lmt;
            }

            if ($final_width && $final_height) {
                return array($final_width, $final_height);
            }
        }

        return array(FC_MAX_IMAGE_POPUP_WIDTH, FC_MAX_IMAGE_POPUP_HEIGHT);
    }

    function getJSView($thumbnail=FALSE)
    {
        if ($thumbnail) {
            $values['label'] = $this->getThumbnail();
        } else {
            $values['label'] = sprintf('<img src="images/mod/filecabinet/viewmag+.png" width="16" height="16" title="%s" />',
                                   dgettext('filecabinet', 'View full image'));
        }

        $size = $this->popupSize();

        $values['address']     = $this->popupAddress();
        $values['width']       = $size[0];
        $values['height']      = $size[1];
        $values['window_name'] = 'image_view';

        return Layout::getJavascript('open_window', $values);
    }

    function popupAddress()
    {
        if (MOD_REWRITE_ENABLED) {
            return sprintf('filecabinet/%s/image', $this->id);
        } else {
            return sprintf('index.php?module=filecabinet&amp;page=image&amp;id=%s', $this->id);
        }

    }

    function thumbnailDirectory()
    {
        return $this->file_directory . 'tn/';
    }

    function thumbnailPath()
    {
        return $this->thumbnailDirectory() . $this->thumbnailName();
    }

    function getTag()
    {
        $tag[] = '<img';

        $tag[] = 'src="'    . $this->getPath() . '"';
        $tag[] = 'alt="'    . $this->getAlt(TRUE)   . '"';
        $tag[] = 'title="'  . $this->title . '"';
        $tag[] = 'width="'  . $this->width     . '"';
        $tag[] = 'height="' . $this->height    . '"';
        $tag[] = 'border="' . $this->border    . '"';
        $tag[] = '/>';
        return implode(' ', $tag);
    }

    function getThumbnail()
    {
        return sprintf('<img src="%s" title="%s" />',
                       $this->thumbnailPath(),
                       $this->title);
    }


    function loadAllowedTypes()
    {
        $this->_allowed_types = unserialize(ALLOWED_IMAGE_TYPES);
    }


    function thumbnailName()
    {
        return preg_replace('/\.(jpg|gif)$/', '.png', $this->file_name);
    }


    /**
     * This is a modified version of the script written by feip at feip dot net.
     * It was copied from php.net at:
     * http://www.php.net/manual/en/function.imagecopyresized.php
     */
    function resize($dst, $new_width, $new_height, $force_png=false) {
        if (!extension_loaded('gd')) {
            if (!dl('gd.so')) {
                @copy(PHPWS_HOME_DIR . 'images/mod/filecabinet/nogd.png', $dst);
                return true;
            }
        }

        $source_image_path = $this->getPath();

        if ( ($this->width < $new_width) &&
             ($this->height < $new_height) ) {
            return @copy($source_image_path, $dst);
        }

        if ($this->file_type == 'image/gif') {
            $source_image = imagecreatefromgif($source_image_path);
        } elseif ($this->file_type == 'image/jpeg') {
            $source_image = imagecreatefromjpeg($source_image_path);
        } elseif ($this->file_type == 'image/png') {
            $source_image = imagecreatefrompng($source_image_path);
        } else {
            return false;
        }

        $proportion_X = $this->width / $new_width;
        $proportion_Y = $this->height / $new_height;

        if($proportion_X > $proportion_Y ){
            $proportion = $proportion_Y;
        }else{
            $proportion = $proportion_X ;
        }

        $target['width'] = $new_width * $proportion;
        $target['height'] = $new_height * $proportion;

        $original['diagonal_center'] =
            round(sqrt(($this->width*$this->width)+($this->height*$this->height))/2);
        $target['diagonal_center'] =
            round(sqrt(($target['width']*$target['width'])+
                       ($target['height']*$target['height']))/2);

        $crop = round($original['diagonal_center'] - $target['diagonal_center']);

        if($proportion_X < $proportion_Y ){
            $target['x'] = 0;
            $target['y'] = round((($this->height/2)*$crop)/$target['diagonal_center']);
        }else{
            $target['x'] =  round((($this->width/2)*$crop)/$target['diagonal_center']);
            $target['y'] = 0;
        }


        if(PHPWS_File::chkgd2()) {
            $resampled_image = imagecreatetruecolor($new_width, $new_height);
            imagealphablending($resampled_image, false);
            imagesavealpha($resampled_image, true);
        } else {
            $resampled_image = imagecreate($new_width, $new_height);
        }


        imagecopyresampled($resampled_image,  $source_image,  0, 0, $target['x'],
                            $target['y'], $new_width, $new_height, $target['width'], $target['height']);

        imagedestroy($source_image);

        if ($force_png && $this->file_type == 'image/png') {
            return imagepng($resampled_image, $dst);
        } elseif ($this->file_type == 'image/gif') {
            return imagegif($resampled_image, $dst);
        } elseif ($this->file_type == 'image/jpeg') {
            return imagejpeg($resampled_image, $dst);
        } else {
            return FALSE;
        }
    }

    function makeThumbnail()
    {
        return $this->resize($this->thumbnailPath(), 100, 100, true);
    }


    function delete()
    {
        $db = new PHPWS_DB('images');
        $db->addWhere('id', $this->id);
        $result = $db->delete();
        if (PEAR::isError($result)) {
            return $result;
        }
        
        $path = $this->getPath();

        if (!@unlink($path)) {
            PHPWS_Error::log(FC_COULD_NOT_DELETE, 'filecabinet', 'PHPWS_Image::delete', $path);
        }

        $tn = $this->thumbnailPath();
        if (!@unlink($tn)) {
            PHPWS_Error::log(FC_COULD_NOT_DELETE, 'filecabinet', 'PHPWS_Image::delete', $path);
        }

        return true;
    }

    function pinTags()
    {
        $tpl['TN'] = $this->getJSView(true);
        return $tpl;
    }
    
    function rowTags()
    {
        if (Current_User::allow('filecabinet', 'edit_folder', $this->folder_id)) {
            $links[] = PHPWS_Text::secureLink(dgettext('filecabinet', 'Clip'), 'filecabinet',
                                              array('aop'=>'clip_image',
                                                    'image_id' => $this->id));
            
            $vars['aop'] = 'upload_image_form';
            $vars['image_id'] = $this->id;
            $vars['folder_id'] = $this->folder_id;
            
            $jsvars['width'] = 550;
            $jsvars['height'] = 480;
            $jsvars['address'] = PHPWS_Text::linkAddress('filecabinet', $vars, true);

            $jsvars['label'] = dgettext('filecabinet', 'Edit');
            $links[] = javascript('open_window', $jsvars);
        
            $vars['aop'] = 'delete_image';
            $js['QUESTION'] = dgettext('filecabinet', 'Are you sure you want to delete this image?');
            $js['ADDRESS']  = PHPWS_Text::linkAddress('filecabinet', $vars, true);
            $js['LINK']     = dgettext('filecabinet', 'Delete');
            $links[] = javascript('confirm', $js);
        
            $tpl['ACTION'] = implode(' | ', $links);
        }

        $tpl['SIZE'] = $this->getSize(TRUE);
        $tpl['FILE_NAME'] = $this->file_name;
        $tpl['THUMBNAIL'] = $this->getJSView(TRUE);
        $tpl['TITLE']     = $this->title;
        $tpl['DIMENSIONS'] = sprintf('%s x %s', $this->width, $this->height);
        
        return $tpl;
    }
    
    function xmlFormat()
    {
        $values = PHPWS_Core::stripObjValues($this);

        foreach ($values as $key=>$value) {
            if (substr($key, 0, 1) == '_') {
                continue;
            }
            $tpl['rows'][] = array('key'=>$key, 'value'=>addslashes($value));
        }
        $tpl['rows'][] = array('key'=>'thumbnail', 'value'=>$this->thumbnailPath());
        return PHPWS_Template::process($tpl, 'filecabinet', 'image.xml');
    }


    function save($no_dupes=TRUE, $write=TRUE)
    {
        if (empty($this->file_directory)) {
            if ($this->folder_id) {
                $folder = new Folder($_POST['folder_id']);
                if ($folder->id) {
                    $this->file_directory = $folder->getFullDirectory();
                } else {
                    return PHPWS_Error::get(FC_MISSING_FOLDER, 'filecabinet', 'PHPWS_Image::save');
                }
            } else {
                return PHPWS_Error::get(FC_DIRECTORY_NOT_SET, 'filecabinet', 'PHPWS_Image::save');
            }
        }

        if (!$this->folder_id) {
            return PHPWS_Error::get(FC_MISSING_FOLDER, 'filecabinet', 'PHPWS_Image::save');
        }

        if (!is_writable($this->file_directory)) {
            return PHPWS_Error::get(FC_BAD_DIRECTORY, 'filecabinet', 'PHPWS_Image::save', $this->file_directory);
        }

        if (empty($this->alt)) {
            if (empty($this->title)) {
                $this->title = $this->file_name;
            }
            $this->alt = $this->title;
        }

        if ($write) {
            $result = $this->write();
            if (PEAR::isError($result)) {
                return $result;
            }
        }

        $this->makeThumbnail(100,100);

        $db = new PHPWS_DB('images');

        if ((bool)$no_dupes && empty($this->id)) {
            $db->addWhere('file_name',  $this->file_name);
            $db->addWhere('folder_id', $this->folder_id);
            $db->addColumn('id');
            $result = $db->select('one');
            if (PEAR::isError($result)) {
                return $result;
            } elseif (isset($result) && is_numeric($result)) {
                $this->id = $result;
                return TRUE;
            }

            $db->reset();
        }
        return $db->saveObject($this);
    }


    function setAlt($alt)
    {
        $this->alt = strip_tags($alt);
    }

    function getAlt($check=FALSE)
    {
        if ((bool)$check && empty($this->alt) && isset($this->title)) {
            return $this->title;
        }

        return $this->alt;
    }

    function setMaxWidth($width)
    {
        $this->_max_width = (int)$width;
    }

    function setMaxHeight($height)
    {
        $this->_max_height = (int)$height;
    }
}

?>