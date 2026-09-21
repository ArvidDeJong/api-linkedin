---
title: "Home"
nav_order: 1
description: "darvis/api-linkedin for Laravel publishes posts on a LinkedIn profile or company page through OAuth 2.0 and the Posts API, with encrypted, refreshed tokens."
permalink: /
---

# darvis/api-linkedin

`darvis/api-linkedin` is a Laravel package that publishes posts on **LinkedIn** from your application: on the personal profile of the connected member, or on a company page that member administers. It handles the OAuth 2.0 connect flow, stores the tokens encrypted, refreshes them, and sends the post through the LinkedIn Posts API.

This is an independent open-source package, not affiliated with LinkedIn.

## Who it is for

A Laravel application that wants to share its own content on LinkedIn: a blog that announces new articles, a CMS with a "share on LinkedIn" button, a company site that posts news on its company page.

## What it does not do

- It keeps **one LinkedIn connection for the whole application**, not one per user of your application.
- It only creates posts. It does not read a feed, comments, likes or statistics, and it does not edit or delete posts.
- It does not sign users in to your application with LinkedIn.
- It posts text, optionally with an article card (a link with a title, a description and a thumbnail). It does not create image, video, document or poll posts.
- It has no duplicate check and no queue of its own. Publishing twice posts twice.

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12 or 13
- A LinkedIn app with the products **Share on LinkedIn** and **Sign In with LinkedIn using OpenID Connect**. Posting on a company page also needs the **Community Management API**.

## Install

```bash
composer require darvis/api-linkedin
php artisan migrate
```

Then add `LINKEDIN_CLIENT_ID` and `LINKEDIN_CLIENT_SECRET` to `.env`. [Installation](installation.md) has every step, including the LinkedIn app.

## Where to go next

- [Installation & configuration](installation.md): the LinkedIn app, the `.env` values, every config key, and how to check that it works
- [Quick start](quickstart.md): one complete example, from a connect button to a published post
- [Connecting](connecting.md): who may connect, the flash messages, your own OAuth routes, and what the token may do
- [Publishing](publishing.md): posts on a profile or a company page, article cards with your own image
- [Company pages](company-pages.md): list the pages the member administers and let a user pick one
- [Error handling](errors.md): the exception types and what your code does with each
- [Troubleshooting](troubleshooting.md): look up an error message and find the cause and the fix
- [Testing](testing.md): test your own code without calling LinkedIn
- [FAQ](faq.md): short answers to common questions
