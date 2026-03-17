TMD Event Banner Manager
=======================

What this plugin does
---------------------
- Creates a backend interface with Content / Style / Advanced tabs
- Stores event records in a dedicated DB table
- Resolves the active event by date and priority
- Outputs current_event_slug to the frontend
- Provides a Slider Revolution helper layer for MASTER_EVENT_TEMPLATE automation
- Supports background image upload via WordPress media library

Important note about Slider Revolution integration
--------------------------------------------------
The included Slider Revolution integration is a safe starter skeleton.
Actual layer mutation / template import methods vary by Slider Revolution version.
Use staging first, then connect class-tmd-ebm-slider-helper.php to the installed plugin version.

Required standardized layer names inside MASTER_EVENT_TEMPLATE
--------------------------------------------------------------
- layer_eyebrow
- layer_headline
- layer_subheadline
- layer_discount
- layer_button
- layer_background
- layer_countdown
- layer_trust
