SilverStripe oEmbed Endpoint Provider
===

*This works is not yet licensed. It is free for use by developers, for the purpose of improving this works, however you may not yet use it for production purposes.*

Usage
---

Any class can provide an oEmbed endpoint by extending the abstract class `oEmbedEndpoint`, implementing the required members.

The static variable `$scheme` represents the oEmbed-provider-style scheme format (e.g. http://example.com/*) for a provider.

The static method `oEmbedProvider::get_response` accepts a url, and optionally accepts an array of options, sourced from the query parameters of the call.  
This method must return an instance of a class which extends `oEmbedEndpoint_Response`.
