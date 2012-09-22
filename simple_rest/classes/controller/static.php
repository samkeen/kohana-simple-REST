<?php defined('SYSPATH') or die('No direct script access.');
/**
 * This is the default static controller.  i.e. someone makes a non
 * /api/v1 request to this app.
 * This should be a welcome page for the frijee api meant for
 * human consumption
 */
class Controller_Static extends Controller {

	public function action_index()
	{
		$this->response->body("Hello Human, welcome to the Frijee API");
	}

}