<?php defined('SYSPATH') or die('No direct script access.');

abstract class Api_Controller extends Kohana_Controller {

    /**
     * @var string Meant to be overridden in Concrete
     * Controller init() implementation
     */
    protected static $table_name = '';
    /**
     * @var array These are defined in the concrete class
     */
    static $fields = array();
    /**
     * @var array For POST,PUT,PATCH, this is the array of valid
     * field_name => values that will be used for query construction.
     * @see $this->post|put|patch_validate()
     */
    protected $validated_input = array();
    /**
     * @see http://kohanaframework.org/3.2/guide/kohana/security/validation
     * @see http://kohanaframework.org/3.2/guide/api/Validation
     * @var Validation
     */
    protected $validator;
    /**
     * @var string Meant to be overridden in Concrete
     * Controller init() implementation
     */
    protected static $primary_key_field = 'id';

    private $known_request_methods = array(
        'GET',
        'PUT',
        'PATCH',
        'POST',
        'DELETE',
        'HEAD',
        'OPTIONS',
    );

    function __construct(Request $request, Response $response)
    {
        /*
         * Set the default content type to json
         */
        $response->headers('Content-Type', 'application/json');
        if(in_array(strtoupper($request->method()), $this->known_request_methods))
        {
            $request->action(strtolower($request->method()));
        }
        else
        {
            $response->headers('HTTP/1.1', 400 . Util_Http::$code_labels[400]);
            $response->body(
                json_encode(array('__error' => array(
                    '__message' => "HTTP Method {$request->method()} not known."
                        . "Known method: " . implode(', ', $this->known_request_methods)
                )))
            );
            $request->action('error');
        }
        return parent::__construct($request, $response);
    }

    /**
     * Default GET controller for API, override in concrete /api/<controller> to
     * provide support for GET in that controller
     *
     * Default action, throw 405 with supported methods header
     */
    function action_get()
    {
        return $this->error_response(405, 'GET method not allowed');
    }

    /**
     * Default behavior for GET requests.
     *
     * Usage in concrete Controllers
     *
     *  function action_get()
     *  {
     *    return parent::fulfill_get_request();
     *  }
     *
     */
    protected function fulfill_get_request()
    {
        $requested_resource_identifier = $this->request->param('resource_id');
        $query = DB::select()->from(static::$table_name);
        if($requested_resource_identifier)
        {
            $query->where(static::$primary_key_field, '=', $requested_resource_identifier);
        }
        $results = $query->execute();
        if( ! $results->count() && $requested_resource_identifier)
        {
            $this->error_response(404, "Resource with identifier [{$requested_resource_identifier}] Not Found");
        }
        else
        {
            $this->response->body(
                json_encode($results->as_array())
            );
        }
    }

    /**
     * Default PUT controller for API, override in concrete /api/<controller> to
     * provide support for PUT in that controller
     *
     * Default action, throw 405 with supported methods header
     */
    function action_put()
    {
        return $this->error_response(405, 'PUT method not allowed');
    }

    /**
     * Default PATCH controller for API, override in concrete /api/<controller> to
     * provide support for PATCH in that controller
     *
     * Default action, throw 405 with supported methods header
     */
    function action_patch()
    {
        return $this->error_response(405, 'PATCH method not allowed');
    }

    /**
     * Default POST controller for API, override in concrete /api/<controller> to
     * provide support for POST in that controller
     *
     * Default action, throw 405 with supported methods header
     */
    function action_post()
    {
        return $this->error_response(405, 'POST method not allowed');
    }

    /**
     * Default implementation for the validation of POST input. Override
     * in Concrete Controller as needed.
     * The ultimate effect of this method is that $this->validated_input is
     * populated to be used in query creation.  It ONLY will include keys that
     * are known to self::$fields.
     * If you need to add/subtract/mutate $this->validated_input, do that after this call
     * in your controller.
     *
     * i.e.
     * <code>
     * function action_post()
     * {
     *   if( ! $this->post_validate($this->request->post()))
     *   {
     *     $this->end_with_validation_error();
     *   }
     *   else
     *   {
     *     // be sure you validate this key/val in some way
     *     $this->validated_fields['some_other_key'] = 'baz';
     *     return $this->fulfill_post_request();
     *   }
     * }
     * </code>
     *
     * @param $input
     * @return bool
     */
    protected function post_validate(array $input = array())
    {
        $input = array_intersect_key($input, static::$fields);
        $this->init_validations($input);
        $valid = $this->validator->check();
        if($valid)
        {
            $this->validated_input = $this->validator->data();
        }
        return $valid;
    }

    /**
     * Default behavior for POST request.  Override in Concrete Controller
     * as needed.
     *
     * Usage:
     * <code>
     * function action_post()
     * {
     *   if( ! $this->post_validate($this->request->post()))
     *   {
     *     $this->end_with_validation_error();
     *   }
     *   else
     *   {
     *     return $this->fulfill_post_request();
     *   }
     * }
     * </code>
     */
    protected function fulfill_post_request()
    {
        $data = $this->validated_input;
        if( ! $data)
        {
            $this->error_response(400, 'There was no POST data');
            return;
        }
        $sql = Util_Sql::build_insert(static::$table_name, $data);
        $query = DB::query(
            Database::INSERT,
            $sql
        );
        $query_parameters = Util_Arr::prefix_array_key(':', $data);
        $query->parameters($query_parameters);
        try
        {
            list($identifier, $rows_affected) = $query->execute();
            $base_url = Kohana::$base_url;
            $protocol = $this->request->secure() ? 'https' : 'http';
            $this->response->headers('HTTP/1.1', '201 Created');
            echo json_encode(
                array(
                    static::$primary_key_field => $identifier,
                    'link' => array(
                        "href" => "{$protocol}://{$_SERVER['HTTP_HOST']}{$base_url}{$this->request->uri()}/{$identifier}"
                    )
                )
            );
        }
        catch(Database_Exception $e)
        {
            $this->response->headers('HTTP/1.1', '500 Server Error');
        }
    }

    /**
     * default implementation of sending a validation error to client
     */
    protected function validation_error_response()
    {
        /*
         * 'api' is the validation message translation file
         * @see http://kohanaframework.org/3.2/guide/kohana/files/messages
         */
        $errors = $this->validator->errors('api');
        return $this->error_response(400, 'There was validation error', array('__validation' => $errors));
    }

    /**
     * default implementation of sending a validation error to client
     */
    protected function error_response($code, $message, array $additional=array())
    {
        $error_values = array_merge(
            array(
                '__code'    => $code,
                '__message' => $message,
            ),
            $additional
        );
        $this->response->body(json_encode(array('__error' => $error_values)));
        $this->response->headers('HTTP/1.1', $code . Util_Http::$code_labels[$code]);

    }

    /**
     * Default DELETE controller for API, override in concrete /api/<controller> to
     * provide support for DELETE in that controller
     *
     * Default action, throw 405 with supported methods header
     */
    function action_delete()
    {
        return $this->error_response(405, 'DELETE method not allowed');
    }

    /**
     * Default HEAD controller for API, override in concrete /api/<controller> to
     * provide support for HEAD in that controller
     *
     * Default action, throw 405 with supported methods header
     */
    function action_head()
    {
        return $this->error_response(405, 'HEAD method not allowed');
    }

    /**
     * Default OPTIONS controller for API, override in concrete /api/<controller> to
     * provide support for OPTIONS in that controller
     *
     * Default action, throw 405 with supported methods header
     */
    function action_options()
    {
        return $this->error_response(405, 'OPTIONS method not allowed');
    }

    /**
     * hack do to the way Kohana handles responses.
     * Only used in $this->__construct() for HTTP method unknown
     */
    function action_error()
    {
        return;
    }

    /**
     * Apply validation rules defined in Concrete class's $fields array
     *
     * @see http://kohanaframework.org/3.2/guide/kohana/security/validation
     * @see http://kohanaframework.org/3.2/guide/api/Validation
     * @param array $input
     */
    private function init_validations(array $input = array())
    {
        $this->validator = Validation::factory((array)$input);
        foreach((array)static::$fields as $field_name => $rules)
        {
            if($rules)
            {
                $this->validator->rule($field_name, Arr::get($rules, 0), Arr::get($rules, 1));
            }
        }
    }

}