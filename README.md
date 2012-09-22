# kohana-simple-REST
==================

## Summary

Simple JSON only RESTful controller for Kohana in module form

Built against Kohana 3.2 [http://kohanaframework.org/3.2/guide/]

Currently it is hardcoded to only respond in JSON (application/json).

What it is primarily doing is routing the HTTP verbs into the corresponding Kohana actions.

i.e.  

Get /index.php/api/v1/users  --> will route to the controllers/api/users::action_get method

There are also default implementations for GET/POST/PUT/DELETE/PATCH in the base controller @see `fulfill_{method}_request` methods in Api_Controller

## Install