<?php

// custom thumbnailling
add_filter('image_downsize', array('AttachmentGalleryManager', 'image_downsize'), 0, 2);
add_filter('thumbnailable_extensions', array('AttachmentGalleryManager', 'thumbnailable_extensions'), 0, 1);

/**
  * @desc   Attachment Gallery Display Manager
  * @author Leo Brown <leo@acumendevelopment.net>
  * @date   May 24th 2010
  *
  */
Class AttachmentGalleryManager{

	var $path;
	var $options=array();
	var $marker='\[attachmentgallery.*\]';
	var $downloadText='Download Now';

	/**
	  * @desc   
	  * @author Leo Brown
	  *
	  */
	function AttachmentGalleryManager(){
		$this->path = dirname(__FILE__).'/';
	}

	/**
	  * @desc   Gets a callable URL for a thumbnail of PDF etc
	  * @author Leo Brown
	  * @param  
	  * @return string The URL
	  */
	static function get_thumbnail_url($size, $local_url){

		return WP_PLUGIN_URL.'/attachment-gallery/thumbnail.php?'.
			'size='.$size.
			'&file='.$local_url;

	}

	/**
	  * @desc   Get shortcode options since we're retrofitting this and need to detect them later
	  * @author Leo Brown
	  *
	  */
	function thumbnailable_extensions(&$baseTypes){
		$baseTypes[]='pdf';
		return $baseTypes;
	}

	/**
	  * @desc   Get shortcode options since we're retrofitting this and need to detect them later
	  * @author Leo Brown
	  *
	  */
	function image_downsize($false, $id, $size=null){

		// we're not doing this - hand back to Wordpress
		if(true){
//			return image_downsize($id, $size);
		}

		// The original returns array( $img_url, $width, $height, $is_intermediate );
		return array(
			AttachmentGalleryManager::get_thumbnail_url($size, wp_get_attachment_url($id))
		);
	}

	/**
	  * @desc   Get shortcode options since we're retrofitting this and need to detect them later
	  * @author Leo Brown
	  *
	  */
	function detect_gallery_options($content){

		// get shortcode
		$shortcode=array();
		$options=array();
		if(preg_match("/{$this->marker}/", $content, $shortcode)){

			// extract and use shortcode_parse_atts() to get the array
			$shortcode=reset($shortcode);
			preg_match('/\[[^ ]* (.*)\]/', $shortcode, $options);
			$options=shortcode_parse_atts(end($options));

			return $options;
		}
		else return false;
	}

	/**
	  * @desc   Returns gallery HTML on the basis of options
	  * @author Leo Brown
	  * @param  $options Array Options array
	  * @return string HTML of Gallery
	  */
	function get_attachment_gallery(&$contents,$options=array('size'=>'100x150')){

		// get our attachments
		$attachments = $this->find_attachment_links($contents, $options['filetypes']);

		// get metadata reader
		@include_once 'PDFMetaData.class.php';
		if(class_exists('PDFMetaData')) $metaReader=new PDFMetaData();

		// generate HTML for gallery
		$html='';
		if($attachments){

			// downlaod links
			$linkize=($options['download'] && ('no'!==$options['download']));

			// classes
			$class = ' ';
			if(@$options['grid']) $class.="attachment_gallery_grid";

			// output gallery
			$html .= "<div class=\"attachment_gallery{$class}\">";
			foreach($attachments as $file){

				// link to attachment itself, or it's WP page
				$file['link'] = @$options['linktopage'] ? $file['attachment_url'] : $file['url'];

				// manage relative links by prepending http://hostname/
				if(!array_key_exists('host',@parse_url($file['url']))){
					$file['url']="http://{$_SERVER['HTTP_HOST']}/{$file['url']}";
				}

				$meta = $this->cache_get('attachment_gallery_'.md5($file['url']));
				if(!is_array($meta)){
					if(@$metaReader){
						$meta = $metaReader->getMeta($file['url']);
						$this->cache_set('attachment_gallery_'.md5($file['url']),$meta);
					}
				}

				// output the base HTML for this attachment
				$html .= "<div class=\"attachment_gallery_item\">";
				if(class_exists('imagick')){
					$html .= '<div class="attachment_gallery_item_thumbnail">';
						if($linkize) $html .= '<a href="'.$file['link'].'">';

							$html .= '<img src="'. $this->get_thumbnail_url(
								$options['size'],
								$file['url']
							).'" />';

						if($linkize) $html .= '</a>';
					$html .= '</div>';
				}
				$html .="<div class=\"attachment_gallery_item_title\">";
					if($linkize) $html .= "<a href=\"{$file['link']}\">";
						$html .= $file['title'];
					if($linkize) $html .= '</a>';
					$html .= "</div>";

				// overwrite any extracted meta with WP-based meta
				// (i.e. Author, Subject, Keywords etc)
				// so if they give description=Author, the PDF "Author" tag will become
				// that from the Description field of the WP Media Library
				$replacements=array();
				parse_str(html_entity_decode(@$options['metareplace']), $replacements);
				if($replacements) foreach($replacements as $from=>$to){
					if($val = @$file[$from]) $meta[$to] = $val;
				}
				else $meta=array_merge($meta,$file);

				if($meta) foreach(@$meta as $key=>$value){
					// split multiple lines onto DIVs so the user can style them if required
					$valueLines = explode("\n",str_replace(array('\r\n','\n'),"\n",$value));
					$value = '<div class="nl">'.implode('</div><div class="nl">',$valueLines).'&nbsp;</div>';

					$keyNiceName=strtolower(str_replace(' ','_',$key));
					$html .="<dl class=\"attachment_gallery_meta attachment_gallery_meta_".$keyNiceName."\">
						<dt>{$key}</dt> <dd>{$value}</dd>
						</dl>";
				}

				if($linkize) $html .= "<div class=\"attachment_gallery_item_download\">
						<a href=\"{$file['link']}\">{$this->downloadText}</a>
					</div>";

				$html .= "</div>";
			}
			$html .= '</div>';

			if(@$options['grid']) $html .= "<style type=\"text/css\">
				.attachment_gallery_grid .attachment_gallery_item{
				        float:left;
				        clear:none;
				        width:245px;
				}
				.attachment_gallery_grid .attachment_gallery_item_thumbnail{
				        float:none;
				}
			</style>";


		}
		return $html;
	}
	
	/**
	  * @desc   Places items the cache
	  * @author Leo Brown
	  *
	  */
	function cache_set($key, $data){
		return @file_put_contents(
			sys_get_temp_dir().'/attachment_cache_'.
			md5($key),serialize($data)
		);
	}

	/**
	  * @desc   Accesses the cache
	  * @author Leo Brown
	  *
	  */
	function cache_get($key){

		return @unserialize(@file_get_contents(
			sys_get_temp_dir().
			'/attachment_cache_'.md5($key)
		));
	}

	/**
	  * @desc   Splices gallery content back into page content
	  * @author Leo Brown
	  * @todo   Move this function to the shortcode library, probably
	  *
	  */
	function insert_gallery($page, $gallery){
		return preg_replace("/{$this->marker}/",$gallery,$page);
	}
	
	/**
	  * @desc   
	  * @author Leo Brown
	  *
	  */
	function find_attachment_links(&$input, $types){


// not yet used
//		if(!$types) $types=array('.pdf');
//		$types=implode($types,'|');

		// our list of files
		$results=array();

		// pattern for any link - hand written / may need to be modified
		$regexp = "<a\s[^>]*href=(['\"]??)([^\"\' >]*)\\1[^>]*>(.*)<\/a>";

		// process our matches
		if(preg_match_all("/$regexp/siU", $input, $matches, PREG_SET_ORDER)) {

			foreach($matches as &$match){

				// storage for this result
				$result=array();

				// known values
				$result['title']=$match[3];
				$result['url']  =$match[2];

				// test if is rel=attachment (regex from _fix_attachment_links in post.php)
				$search = "#[\s]+rel=(\"|')(.*?)wp-att-(?P<id>\d+)\\1#i";
				if(preg_match($search, $match[0], $attachment)){

					// get the attachment that this link refers to
					if($data = get_post($attachment['id'], 'ARRAY_A')){
						$result['title']       = $data['post_title'];
						$result['url']         = $data['guid'];
						$result['description'] = $data['post_content'];
						$result['caption']     = $data['post_excerpt'];

						// get author etc too?

					}

					// save this into our list
					$result['attachment_id']=$attachment['id'];
					$result['attachment_url']=get_attachment_link($attachment['id']);
				}

				// only store if we actually have a URL that has a file type from the anchor tag
				// test for a dot in the filename
				if($result['url'] && preg_match('/.*\.[^.]/',$result['url'])){

					// remove the link
					$input=str_replace($match[0],'',$input);

					// store result
					$results[]=$result;
				}
			}
		}

		return $results;
	}
}

/**
  * @desc   Hooks the_content and absorbs attachment links, replaces them with a gallery
  * @author Leo Brown
  *
  */
function attachmentGalleryHook($content){
	$manager=new AttachmentGalleryManager();
	$options = $manager->detect_gallery_options($content);

	if(is_array($options)){
		$gallery = $manager->get_attachment_gallery($content,$options);
		if($gallery){
			return $manager->insert_gallery($content, $gallery);
		}
		else return $content;
	}
	else return $content;
}
?>
