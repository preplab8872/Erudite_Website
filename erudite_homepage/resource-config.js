/*
 * ResourceManager API connection.
 * The rest of the Erudite site uses Laravel's same-origin /api routes.
 * Keep resources on the same origin so this works on localhost and production
 * without hardcoding a development port.
 */
window.ERUDITE_RESOURCE_API_BASE = '/api';
