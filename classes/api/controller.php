<?php defined('SYSPATH') or die('No direct script access.');
/**
 * All the base Request method actions return
 *    $this->error_response(405, 'GET method not allowed');
 * To have your controller support a given HTTP verb, override that method
 * in the concrete class
 */
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

    /**
     * Sets response Content-Type to 'application/json' and checks that
     * the request method is one of the supported HTTP request
     * methods ($this->known_request_methods).  If it is, sets
     * $this->action to Request::method()
     *
     * @param Request $request
     * @param Response $response
     */
    function __construct(Request $request, Response $response)
    {
        /*
         * Set the Response HTTP Header 'Content-Type' to application/json
         */
        $response->headers('Content-Type', 'application/json');
        if(in_array(strtoupper($request->method()), $this->known_request_methods))
        {
            $request->action(strtolower($request->method()));
        }
        else
        {
            $message = "HTTP Method {$request->method()} not known."
                . "Known method: " . implode(', ', $this->known_request_methods);
            $this->route_to_error($request, $response, 400, $message);
        }
        /*
         * Only POSTs with Content-Type = form-encode are 'auto' parsed to an array()
         * and set to $request->post().  So for the other HTTP verbs with payloads (and
         * POST with JSON content type), parse and set to $request->post().
         *
         * Easiest way to get access to that data WITHOUT overriding the Kohana
         * Request class.
         */
        $this->parse_payload($request, $response);
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
     * <code>
     *  function action_get()
     *  {
     *    return parent::fulfill_get_request();
     *  }
     * </code>
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
        /*
         * trim off unknown fields
         */
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
     * @param array $input
     * @return bool
     */
    protected function patch_validate(array $input = array())
    {
        $valid = false;
        $requested_resource_identifier = $this->request->param('resource_id');
        /*
         * trim off unknown fields
         */
        $input = array_intersect_key($input, static::$fields);
        $this->init_validations($input);
        if( ! $requested_resource_identifier)
        {
            $this->validator->error(static::$primary_key_field,
                "PATCH.missing_identifier");
            return $valid;
        }
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
        /*
         * add labels for system controller fields
         */
        $this->validator->label(static::$primary_key_field, static::$primary_key_field);
        foreach((array)static::$fields as $field_name => $rules)
        {
            foreach((array)$rules as $index_or_rule => $rule_name_or_rule_meta)
            {
                /*
                 * Rule definitions can be in on of 2 forms,
                 *  - [Form A] Rule name with no rule parameters
                 *  - [Form B] Rule name with array of rule parameters
                 * ex:
                 * 'raw_text' => array(
                      'not_empty',                          // Form A
                      'max_length' => array(':value', 500)  // Form B
                    ),
                 */
                if( ! is_int($index_or_rule)) // true form Form B
                {
                    //                                     rule name           rule meta
                    $this->validator->rule($field_name, $index_or_rule, $rule_name_or_rule_meta);
                }
                else // Form A
                {
                    //                                         rule name
                    $this->validator->rule($field_name, $rule_name_or_rule_meta);
                }
            }
        }
    }

    /**
     * Used internally to send error responses
     *
     * @param Request $request
     * @param Response $response
     * @param int $error_code One of Util_Http::$code_labels
     * @param string $message
     */
    protected function route_to_error($request, $response, $error_code, $message)
    {
        $response->headers('HTTP/1.1', $error_code . Util_Http::$code_labels[$error_code]);
        $response->body(
            json_encode(array('__error' => array(
                '__message' => $message
            )))
        );
        $request->action('error');
    }

    /**
     * On successful parse, this method sets the resulting array to $request->post();
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    private function parse_payload(Request $request, Response $response)
    {
        $successful_parse = false;
        $parsed_payload = array();
        $request_content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : null;
        $request_payload_body = $request->body();
        $request_method = $request->method();
        /*
         * If is a method that carries a payload, and the payload is not empty
         */
        if(   in_array($request_method, array('POST', 'PUT', 'PATCH', 'DELETE'))
           && trim($request_payload_body)!='')
        {
            switch($request_content_type)
            {
                case Util_Http::CONTENT_TYPE_FORM_ENCODE:
                    /*
                     * if it is POST, $request->post() is already set
                     */
                    if( ! $request_method = 'POST')
                    {
                        parse_str($request_payload_body, $parsed_payload);
                        if( ! $parsed_payload)
                        {
                            $message = "HTTP entity body failed to parse as '" . Util_Http::CONTENT_TYPE_FORM_ENCODE
                                . "' Entity body received was: '{$request_payload_body}'";
                            $this->route_to_error($request, $response, 400, $message);
                        }
                        else
                        {
                            $request->post($parsed_payload);
                            $successful_parse = true;
                        }
                    }
                    break;
                case Util_Http::CONTENT_TYPE_JSON:
                    $parsed_payload = json_decode($request_payload_body, $as_array=true);
                    if(json_last_error())
                    {
                        $message = "HTTP entity body failed to parse as '" . Util_Http::CONTENT_TYPE_JSON
                            . "'  Check syntax and retry request";
                        $this->route_to_error($request, $response, 400, $message);
                    }
                    else
                    {
                        $request->post($parsed_payload);
                        $successful_parse = true;
                    }
                    break;
                default:
                    $header_value = is_null($request_content_type) ? "<missing>" : "'{$request_content_type}'";
                    $message = "Unknown or missing 'Content-Type' HTTP header value."
                        . "  Value found: {$header_value}  Supported Content-Type are'"
                        . Util_Http::CONTENT_TYPE_JSON . "' and '" . Util_Http::CONTENT_TYPE_FORM_ENCODE . "'";
                    $this->route_to_error($request, $response, 400, $message);
            }
        }
        return $successful_parse;
    }

}