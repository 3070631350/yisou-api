<?php

namespace app\controller;

use app\BaseController;

class Search extends BaseController
{
    /**
     * 获取搜索结果列表
     */
    public function index()
    {
        $key = $this->request->param('key', '');
        if (empty($key)) {
            return json(['code' => 400, 'message' => '搜索关键字不能为空', 'type' => 'error']);
        }
        // 拼接$key到URL
        $url = 'https://soupian.one/search' . '?key=' . urlencode($key);
        // 获取网页内容
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

        // 提取搜索结果
        $searchResults = $this->extractSerachResults($xpath);

        return json(['code' => 200, 'msg' => '获取网页内容成功', 'data' => $searchResults]);
    }

    /**
     * 获取推荐和热门
     */
    public function get_recommend_and_hot()
    {
        $url = 'https://soupian.one/search';
        // 获取网页内容
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

        // 提取推荐内容
        $recommendations = $this->extractRecommendations($xpath);
        // 提取热门搜索内容
        $hotSearches = $this->extractHotSearches($xpath);
        $data = [
            'recommend' => $recommendations,
            'hot' => $hotSearches,
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
     * 提取搜索结果
     *
     * @param \DOMXPath $xpath
     * @return array
     */
    private function extractSerachResults($xpath)
    {
        // 初始化搜索结果数组
        $results = [];

        // 获取搜索结果,包含类名为selected的div元素下所有list-row-info元素
        $listRowInfos = $xpath->query('//div[contains(@class, "list-row tab-list selected")]//div[@class="list-row-info"]');

        foreach ($listRowInfos as $listRowInfo) {
            // 获取标题
            $titleNode = $xpath->query('.//h4', $listRowInfo)->item(0);
            $title = $titleNode ? trim(preg_replace('/\s+/', '', $titleNode->nodeValue)) : '';

            // 获取图片链接
            /** @var DOMElement $imgNode */
            $imgNode = $xpath->query('.//img', $listRowInfo)->item(0);
            $image = $imgNode ? $imgNode->getAttribute('src') : '';
            // 如果图片链接是以/static开头的，则拼接上完整URL
            if (strpos($image, '/static') === 0) {
                $image = 'https://soupian.one' . $image;
            }

            // 获取标签
            $tagNode = $xpath->query('.//strong', $listRowInfo)->item(0);
            $tag = $tagNode ? trim($tagNode->nodeValue) : '';

            // 获取平台
            // 获取平台信息
            $platNode = $xpath->query('.//p', $listRowInfo)->item(0);
            $plat = '';
            if ($platNode) {
                // 获取<p>标签下所有文本节点
                $platTextNodes = $platNode->childNodes;
                // 循环遍历文本节点，拼接文本内容，忽略认证和合作站等内容
                foreach ($platTextNodes as $node) {
                    // 只获取文本节点内容，忽略认证和合作站等节点
                    if ($node->nodeType === XML_TEXT_NODE) {
                        $plat .= trim($node->nodeValue);
                    }
                }
                // 去除平台信息中的 "-" 之前的内容
                $plat = substr($plat, strpos($plat, '-') + 1);
            }

            // 获取链接
            /** @var DOMElement $linkNode */
            $linkNode = $xpath->query('.//a[@class="list-row-text"]', $listRowInfo)->item(0);
            $href = $linkNode ? $linkNode->getAttribute('href') : '';
            $href = strtok($href, '?');

            // 添加到结果数组
            $results[] = [
                "title" => $title,
                "image" => $image,
                "tag" => $tag,
                "plat" => $plat,
                "href" => $href,
            ];
        }
        return $results;
    }

    /**
     * 提取推荐内容
     *
     * @param \DOMXPath $xpath
     * @return array
     */
    private function extractRecommendations($xpath)
    {
        $results = [];
        $recommendationNodes = $xpath->query('//div[contains(@class, "wrap-side")]//div[contains(@class, "side-content wrap-inner") and .//h3[text()="推荐"]]//a[@class="poster-item"]');

        foreach ($recommendationNodes as $node) {
            $titleNode = $xpath->query('.//div[@class="poster-item-title"]', $node)->item(0);
            $title = $titleNode ? trim($titleNode->nodeValue) : '';
            /** @var DOMElement $imgNode */
            $imgNode = $xpath->query('.//img', $node)->item(0);
            $image = $imgNode ? $imgNode->getAttribute('src') : '';

            $results[] = [
                'title' => $title,
                'image' => $image,
            ];
        }

        return $results;
    }

    /**
     * 提取热门搜索内容
     *
     * @param \DOMXPath $xpath
     * @return array
     */
    private function extractHotSearches($xpath)
    {
        $results = [];
        $hotSearchNodes = $xpath->query('//div[contains(@class, "wrap-side")]//div[contains(@class, "side-content wrap-inner") and .//h3[text()="热门搜索"]]//a[@class="text-item"]');
        foreach ($hotSearchNodes as $node) {
            $titleNode = $xpath->query('.//span', $node)->item(0);
            $title = $titleNode ? trim($titleNode->nodeValue) : '';

            $results[] = $title;
        }

        return $results;
    }
}
