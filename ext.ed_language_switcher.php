<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Ed_language_switcher_ext
{
	public $settings = array();
	public $name = 'ED language switcher';
	public $version = '0.1';
	public $description = 'Sets a cookie and an early-parsed global variable for chosen language.';
	public $settings_exist = 'y';
	public $docs_url = 'https://github.com/erskinedesign/ed.language_switcher.ee_addon';

	public function __construct($settings = array())
	{
		$this->EE = get_instance();
		$this->settings = $settings;
	}

	public function activate_extension()
	{
	    $default_settings = serialize( $this->default_settings() );

		$this->EE->db->insert(
			'extensions',
			array(
				'class' => __CLASS__,
				'method' => 'sessions_end',
				'hook' => 'sessions_end',
				'settings' => $default_settings,
				'priority' => 10,
				'version' => $this->version,
				'enabled' => 'y'
			)
		);
	}

	public function update_extension($current = '')
	{
		if ( ! $current || $current === $this->version)
		{
			return FALSE;
		}

		$this->EE->db->update(
			'extensions',
			array('version' => $this->version),
			array('class' => __CLASS__)
		);
	}

	public function disable_extension()
	{
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->delete('extensions');
	}

	public function settings()
	{
    	$settings = array();

		$settings['allowed_languages'] = '';

    	return $settings;
	}

	function default_settings()
    {
    	$default_settings = array(
    		'allowed_languages' => 'en|de'
    	);

    	return $default_settings;
    }

	public function sessions_end($OBJ)
	{
		// Assign session object, somewhat dirty but since it's not available in the EE instance
		// at this point, but passed to the session_end() hook... And we need the fetch_current_uri() function
		// http://expressionengine.com/forums/viewthread/168569/#882346
		$this->EE->session =& $OBJ;

		// We set {default_language} in index.php so that subdomains of folders can have different defaults
		$default_language = (
			isset($this->EE->config->_global_vars['default_language']) AND
			! empty($this->EE->config->_global_vars['default_language'])) ? $this->EE->config->_global_vars['default_language'] : 'en';

		$user_language = $default_language;

		// Do we have a language requested in a get variable i.e. lang=de ?
		// If so, use it's value to set the current language
		if ($this->EE->input->get('lang') AND $this->is_allowed_language($this->EE->input->get('lang')))
		{
			$user_language = $this->EE->input->get('lang');

			// Set a cookie to save the user's choice
			$this->EE->functions->set_cookie('user_language', $user_language, 60*60*24*90);

			// redirect to an url with the user_language param
			$this->redirect_language($default_language, $user_language);
		}

		// Do we have a language set as a cookie?
		// If so, use it's value to set the current language
		if ($this->EE->input->cookie('user_language') AND $this->is_allowed_language($this->EE->input->cookie('user_language')))
		{
			$user_language = $this->EE->input->cookie('user_language');

			// we only want to redirect once, initially
			if ( ! $OBJ->flashdata('initial_redirect'))
			{
				// set session data, to prevent multiple initial redirects
				$OBJ->set_flashdata('initial_redirect', TRUE);

				// redirect to an url with the user_language param
				$this->redirect_language($default_language, $user_language);
			}
		}

		// Set the user language as a global variable to use in the templates
		$this->EE->config->_global_vars['user_language'] = $user_language;

	}

	private function is_allowed_language($value)
	{
	    $langs = explode('|', $this->settings['allowed_languages']);
		return in_array($value, $langs);
	}

	private function redirect_language($default_language, $user_language)
	{
		// get urls
		$site_index   = $this->EE->functions->fetch_site_index();
		$url_current  = $this->EE->functions->fetch_current_uri();
		$lang_prefix  = $user_language === $default_language ? '' : $user_language. '/';

		// compose target url
		$url_composed = str_replace($site_index, $lang_prefix, $url_current);

		// add trailing slash to the current url if not present
		if (substr($url_current, -1) != '/')
		{
			$url_current .= '/';
		}

		// add user_language at the end of site_index
		if ($url_current === $site_index AND $default_language !== $user_language)
		{
			$url_composed .= '/'. $user_language;
		}

		// prepend site_index if not on starting page
		if ($url_current !== $site_index)
		{
			$url_composed = $site_index.$url_composed;
		}

		$this->EE->load->helper('url');
		redirect($url_composed);
	}
}

/* End of file ext.ed_language_switcher.php */
/* Location: ./system/expressionengine/third_party/ed_language_switcher/ext.ed_language_switcher.php */