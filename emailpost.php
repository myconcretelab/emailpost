<?php
namespace Grav\Plugin;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Page\Page;
use Grav\Common\Plugin;
use Grav\Common\Utils;
use Grav\Framework\Psr7\Response;
use RocketTheme\Toolbox\Event\Event;

class EmailpostPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $this->enable([
            'onPagesInitialized' => ['onPagesInitialized', 0],
        ]);
    }

    public function onPagesInitialized(Event $event): void
    {
        $uri = $this->grav['uri'];
        $request = $this->grav['request'];
        $route = trim($this->config->get('plugins.emailpost.webhook_route', '/emailpost'), '/');

        if (trim($uri->path(), '/') !== $route) {
            return;
        }

        if ($request->getMethod() !== 'POST') {
            $this->sendResponse(405, 'Method Not Allowed', $event);
            return;
        }

        try {
            $this->handleIncomingMail();
            $this->sendResponse(200, 'Post created', $event);
        } catch (\RuntimeException $e) {
            $this->grav['log']->error('[Emailpost] ' . $e->getMessage());
            $this->sendResponse(400, $e->getMessage(), $event);
        } catch (\Throwable $e) {
            $this->grav['log']->critical('[Emailpost] ' . $e->getMessage());
            $this->sendResponse(500, 'Internal Server Error', $event);
        }
    }

    protected function handleIncomingMail(): void
    {
        $parentRoute = $this->config->get('plugins.emailpost.parent_route', '/blog');
        $parent = $this->grav['pages']->find($parentRoute);

        if (!$parent instanceof Page) {
            throw new \RuntimeException('Blog parent page not found');
        }

        $subject = $this->getRequestValue(['subject', 'Subject']);
        $content = $this->getRequestValue(['stripped-html', 'body-html', 'stripped-text', 'body-plain']);

        if (empty($subject)) {
            throw new \RuntimeException('Missing email subject');
        }

        if (empty($content)) {
            $content = '*Email contained no body*';
        }

        $slug = Utils::slug($subject) ?: date('YmdHis');
        $folderPrefix = date('YmdHis');
        $folderName = $folderPrefix . '-' . $slug;

        $template = $this->config->get('plugins.emailpost.template', 'item');

        $page = new Page();
        $page->extension('.md');
        $page->template($template);
        $page->name($template . '.md');
        $page->title($subject);
        $page->slug($slug);
        $page->path($parent->path() . '/' . $folderName);
        $page->parent($parent);
        $page->setRawContent($content);

        $header = $page->header();
        $header->date = date('Y-m-d H:i');
        $header->published = true;
        $page->header($header);

        $pagePath = $page->path();
        if (empty($pagePath)) {
            throw new \RuntimeException('Unable to determine page path');
        }
        if (!is_dir($pagePath)) {
            Folder::create($pagePath);
        }

        if (!is_dir($pagePath)) {
            throw new \RuntimeException('Unable to create page directory');
        }

        $this->storeAttachments($pagePath);

        $page->save();
        $this->grav['cache']->clearCache('index');
    }

    protected function storeAttachments(string $pagePath): void
    {
        if (empty($_FILES)) {
            return;
        }

        foreach ($_FILES as $file) {
            if (is_array($file['name'])) {
                $fileCount = count($file['name']);
                for ($i = 0; $i < $fileCount; $i++) {
                    $this->moveUploadedFile([
                        'name' => $file['name'][$i] ?? null,
                        'tmp_name' => $file['tmp_name'][$i] ?? null,
                    ], $pagePath);
                }
            } else {
                $this->moveUploadedFile($file, $pagePath);
            }
        }
    }

    protected function moveUploadedFile(array $file, string $pagePath): void
    {
        if (empty($file['tmp_name']) || empty($file['name'])) {
            return;
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            return;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $basename = pathinfo($file['name'], PATHINFO_FILENAME);
        $sanitized = Utils::slug($basename ?: 'attachment');
        $filename = $sanitized . ($extension ? '.' . strtolower($extension) : '');
        $destination = $pagePath . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('Unable to store attachment: ' . $file['name']);
        }
    }

    protected function getRequestValue(array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($_POST[$key]) && $_POST[$key] !== '') {
                return trim((string) $_POST[$key]);
            }
        }

        return null;
    }

    protected function sendResponse(int $status, string $message, Event $event): void
    {
        $payload = json_encode(['status' => $status, 'message' => $message]);
        if ($payload === false) {
            $payload = '{"status":' . $status . ',"message":"JSON encoding error"}';
        }

        $response = new Response($status, ['Content-Type' => 'application/json'], $payload);

        if (isset($this->grav['debugger'])) {
            $this->grav['debugger']->addMessage('[Emailpost] ' . $message);
        }

        $event->stopPropagation();
        $this->grav->close($response);
    }
}
