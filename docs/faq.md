---
title: "FAQ"
nav_order: 10
description: "Short answers about darvis/api-linkedin: what it is, the versions and LinkedIn products it needs, who may connect, expired tokens and company pages."
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
