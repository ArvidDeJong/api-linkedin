---
title: FAQ
nav_order: 7
description: Short answers about darvis/api-linkedin, LinkedIn OAuth scopes, company pages and publishing from Laravel.
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
