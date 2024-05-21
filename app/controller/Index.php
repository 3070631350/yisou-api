<?php

namespace app\controller;

use app\BaseController;

class Index extends BaseController
{
    /**
     * 获取首页内容
     */
    public function get_index()
    {
        // 获取网页内容的 URL
        $url = 'https://soupian.one/';
        $html = $this->fetchWebPage($url);

        if ($html === false) {
            return json(['code' => 400, 'msg' => '获取网页内容失败']);
        }

        // 创建DOMDocument和DOMXPath对象
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        // 获取首页热门电影和友情链接
        $homeHot = $this->extractHomeHot($xpath);
        $blogroll = $this->extractBlogroll($xpath);

        $data = [
            'hot' => $homeHot,
            'blogroll' => $blogroll,
        ];

        return json(['code' => 200, 'msg' => '获取网页内容成功', 'data' => $data]);
    }

    /**
     * 获取网页内容
     *
     * @param string $url
     * @return string|false
     */
    private function fetchWebPage($url)
    {
        return file_get_contents($url);
    }

    /**
     * 提取首页热门电影
     *
     * @param \DOMXPath $xpath
     * @return array
     */
    private function extractHomeHot($xpath)
    {
        $results = [];
        $listWrappers = $xpath->query('//div[contains(@class, "list wrap-inner")]');

        foreach ($listWrappers as $listWrapper) {
            $typeNode = $xpath->query('.//div[@class="list-header"]/h3', $listWrapper)->item(0);
            if ($typeNode) {
                $type = trim($typeNode->nodeValue);
            } else {
                continue;
            }

            $typeData = [
                "type" => $type,
                "list" => [],
            ];

            $posterItems = $xpath->query('.//a[@class="poster-item"]', $listWrapper);

            foreach ($posterItems as $posterItem) {
                $titleNode = $xpath->query('.//div[@class="poster-item-title"]', $posterItem)->item(0);
                $title = $titleNode ? trim($titleNode->nodeValue) : '';
                /** @var DOMElement $imgNode */
                $imgNode = $xpath->query('.//img', $posterItem)->item(0);
                $image = $imgNode ? $imgNode->getAttribute('src') : '';

                $typeData['list'][] = [
                    "title" => $title,
                    "image" => $this->fixImageUrl($image),
                ];
            }

            $results[] = $typeData;
        }

        return $results;
    }

    /**
     * 提取友情链接
     *
     * @param \DOMXPath $xpath
     * @return array
     */
    private function extractBlogroll($xpath)
    {
        $linksArray = [];
        $links = $xpath->query('//div[@class="list-text-box"]/a');

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $name = $link->nodeValue;

            $href = preg_replace('/\?.*/', '', $href);

            $linksArray[] = [
                'href' => $href,
                'name' => $name,
            ];
        }

        return $linksArray;
    }

    /**
     * 修正图片URL
     *
     * @param string $url
     * @return string
     */
    private function fixImageUrl($url)
    {
        if (strpos($url, '/static') === 0) {
            return 'https://soupian.one' . $url;
        }
        return $url;
    }
}
