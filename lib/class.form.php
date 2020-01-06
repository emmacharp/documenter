<?php

	class DocumentationForm
	{

		private $page;

		public function __construct($page)
		{
			$this->page = $page;
		}

		public function render() {
			$this->page->setPageType('form');
			$fields = array();

			Administration::instance()->Page->insertBreadcrumbs(
				array(
					Widget::Anchor(
						Widget::SVGIcon('arrow') . __('Documentation'),
						SYMPHONY_URL . '/extension/documenter/'
					)
				)
			);

			// If we're editing, make sure the item exists
			if ($this->page->getContext()[0]) {
				if (!$doc_id = $this->page->getContext()[0]) redirect(URL . '/symphony/extension/documenter/manage/');

				$existing = Symphony::Database()
					->select(['d.*'])
					->from('tbl_documentation', 'd')
					->where(['d.id' => $doc_id])
					->limit(1)
					->execute()
					->next();

				if (!$existing) {
					Administration::instance()->throwCustomError(
						__('The documentation item you requested to edit does not exist.'),
						__('Documentation Item not found'),
						Page::HTTP_STATUS_NOT_FOUND
					);
				}
			}

			// Build the status message
			if (isset($this->page->getContext()[1])) {
				if ($this->page->getContext()[1] == 'saved') {
					$this->page->pageAlert(
						__('Documentation Item updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Documentation</a>',
						array(Widget::Time()->generate(__SYM_TIME_FORMAT__),
							URL . '/symphony/extension/documenter/new/',
							URL . '/symphony/extension/documenter/')
						),
						Alert::SUCCESS
					);
				} else {
					$this->page->pageAlert(
						__('Documentation Item created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Documentation</a>',
						array(Widget::Time()->generate(__SYM_TIME_FORMAT__),
							URL . '/symphony/extension/documenter/new/',
							URL . '/symphony/extension/documenter/')
						),
						Alert::SUCCESS
					);
				}
			}

			// Find values
			if (isset($_POST['fields'])) {
				$fields = $_POST['fields'];

			} else if ($this->page->getContext()[0]) {
				$fields = $existing;
				$fields['content'] = General::sanitize($fields['content']);
			}

			$title = $fields['title'];
			if (trim($title) == '') $title = $existing['title'];

			// Start building the page
			$this->page->setTitle(__(
				($title ? '%1$s &ndash; %2$s &ndash; %3$s' : '%1$s &ndash; %2$s'),
				array(
					__('Symphony'),
					__('Documentation'),
					$title
				)
			));
			$this->page->appendSubheading(($title ? $title : __('Untitled')));

			// Start building the fieldsets
			$fieldset = new XMLElement('fieldset');

			// Title text input
			$label = Widget::Label(__('Title'));
			$label->appendChild(Widget::Input(
				'fields[title]', General::sanitize($fields['title'])
			));

			if (isset($this->page->_errors['title'])) {
				$label = Widget::Error($label, $this->page->_errors['title']);
			}
			$fieldset->appendChild($label);

			// Content textarea
			$label = Widget::Label(__('Content'));

			$content = Widget::Textarea('fields[content]', 30, 80, General::sanitize($fields['content']));
			if (Symphony::Configuration()->get('text-formatter', 'documentation') != 'none') {
				$content->setAttribute('class', General::sanitize(Symphony::Configuration()->get('text-formatter', 'documentation')));
			}
			$label->appendChild($content);
			$fieldset->appendChild((isset($this->page->_errors['content']) ? Widget::Error($label, $this->page->_errors['content']) : $label));

			// Auto generate requires
			if (strpos(Symphony::Configuration()->get('text-formatter', 'documentation'), 'markdown') !== false) {
				$fieldset->appendChild(Widget::Input('autogenerate',
					__('Auto-generate content according to selected section(s)'),
					'button', array('class'=>'button')
				));
			}

			$this->page->Primary->appendChild($fieldset);

			// Pages multi-select
			$fieldset = new XMLElement('fieldset');
			$secondary = new XMLElement('section');
			$secondary->setAttribute('id', 'secondary');
			$label = Widget::Label(__('Pages'));

			if (!is_array($fields['pages'])) {
				$pages_array = explode(',', $fields['pages']);
			} else {
				$pages_array = $fields['pages'];
			}
			$options = array();

			// Generate a list of sectionField-data for auto-generation of documentation:
			$arr = array();

			// Build the options list using the navigation array
			foreach (Administration::instance()->Page->_navigation as $menu) {
				$items = array();
				foreach ($menu['children'] as $item) {
					$items[] = array($item['link'], (in_array($item['link'], $pages_array)), General::sanitize($menu['name']) . " > " . General::sanitize($item['name']));

					// If it's a section, add New and Edit pages
					// NOTE: This will likely break when extensions add custom nav groups
					if ($menu['name'] != 'Blueprints' and $menu['name'] != 'System') {
						$items[] = array($item['link'] . 'new/', (in_array($item['link'] . 'new/', $pages_array)), General::sanitize($menu['name']) . " > " . General::sanitize($item['name']) . " New");
						$items[] = array($item['link'] . 'edit/', (in_array($item['link'] . 'edit/', $pages_array)), General::sanitize($menu['name']) . " > " . General::sanitize($item['name']) . " Edit");
					}

					// Generate a list of sectionField-data for auto-generation of documentation:
					if ($item['type'] == 'section') {
						$arr2 = array('name' => $item['name'], 'link' => $item['link'], 'items' => array());
						$fields = (new FieldManager)
							->select()
							->section($item['section']['id'])
							->execute()
							->rows();
						foreach($fields as $field)
						{
							/* @var $field Field */
							$arr2['items'][] = array('label' => General::sanitize($field->get('label')));
						}
						$arr[] = $arr2;
					}
				}
				$options[] = array('label' => $menu['name'], 'options' => $items);
			}

			Administration::instance()->Page->addElementToHead(new XMLElement('script', 'var sectionFields = '.json_encode($arr).';',
				array('type' => 'text/javascript')));

			$label->appendChild(Widget::Select('fields[pages][]', $options, array('multiple' => 'multiple', 'id' => 'documenter-pagelist')));

			if (isset($this->page->_errors['pages'])) {
				$label = Widget::Error($label, $this->page->_errors['pages']);
			}

			$fieldset->appendChild($label);
			$secondary->appendChild($fieldset);
			$this->page->Form->appendChild($secondary);

			// Form actions
			Administration::instance()->Page->Header->setAttribute('class', 'spaced-bottom');
			Administration::instance()->Page->Context->setAttribute('class', 'spaced-right');
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');

			if($this->page->getContext()[0]){
				$button = new XMLElement('button', Widget::SVGIcon('delete'));
				$button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'confirm delete', 'title' => __('Delete this template')));
				$div->appendChild($button);
			}

			$saveBtn = new XMLElement('button', Widget::SVGIcon('save'));
			$saveBtn->setAttributeArray(array('name' => 'action[save]', 'class' => 'button', 'title' => __('Save this entry'), 'type' => 'submit', 'accesskey' => 's'));
			$div->appendChild($saveBtn);

			$this->page->ContentsActions->appendChild($div);
		}

		function applyFormatting($data, $validate = false, &$errors = null){
			include_once(TOOLKIT . '/class.textformattermanager.php');

			$text_formatter = Symphony::Configuration()->get('text-formatter', 'documentation');

			if ($text_formatter != 'none') {
				$formatter = TextformatterManager::create($text_formatter);
				$result = $formatter->run($data);
			} else {
				$result = $data;
			}

			if ($validate === true) {
				include_once(TOOLKIT . '/class.xsltprocess.php');

				if ($text_formatter == 'none') {
					$result = DocumentationForm::__replaceAmpersands($result);
				} else {
					if (!General::validateXML($result, $errors, false, new XsltProcess)) {
						$result = html_entity_decode($result, ENT_QUOTES, 'UTF-8');
						$result = DocumentationForm::__replaceAmpersands($result);

						if (!General::validateXML($result, $errors, false, new XsltProcess)) {
							$result = $formatter->run(General::sanitize($data));

							if (!General::validateXML($result, $errors, false, new XsltProcess)) {
								return false;
							}
						}
					}
				}
			}

			return $result;
		}

		private function __replaceAmpersands($value){
			return preg_replace('/&(?!(#[0-9]+|#x[0-9a-f]+|amp|lt|gt);)/i', '&amp;', trim($value));
		}
	}
