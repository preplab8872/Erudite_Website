# Database-driven section dropdowns

The only fixed section labels are MBA in India, UG College Test and Study Abroad. Their dropdown entries are loaded from the CRM `forms` table through the existing `GET /api/forms/{section}` endpoint.

For each returned row, `form_name` becomes the dropdown label and `forms` is displayed when selected. No predefined section overview page is used as a form. If a section has no rows, the dropdown displays an empty-state message and contains no form links.

The consultation booking page and its OTP flow are separate and unchanged. The existing Form Manager upload/storage implementation and Laravel API are unchanged.
