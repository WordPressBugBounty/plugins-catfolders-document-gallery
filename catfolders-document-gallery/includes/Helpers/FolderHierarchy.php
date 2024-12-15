<?php
namespace CatFolder_Document_Gallery\Helpers;
class FolderHierarchy {
    private $wpdb;
    private $childrenCache = [];

    public function __construct($wpdb) {
        $this->wpdb = $wpdb;
    }

    // Public function to render the folder hierarchy for a given folder ID
    public function render_hierarchy($folder_id, $limit_parent_id = -1, $show_the_parent = false, $wrap = true) {
        $html = $wrap ? '<div class="cfdoc_breadcrumb">' : '';

        if($show_the_parent) {
            $parents = $this->get_parents($folder_id, $limit_parent_id);
            $html .= '<ul>';

            foreach ($parents as $k => $folder) {
                $children = $this->get_children($folder->id);
                $html .= '<li data-id="'.intval( $folder->id ).'" class="'. (($k == 0) ? 'cfdoc-home-item ' : '') . (!empty($children) ? 'has-children ' : '') .'">'.(($k == 0) ? $this->homeIcon() : '') . '<span>'.esc_html($folder->title).'</span>';
                
                if(!empty($children)) {
                    $html .= $this->downArrowIcon();
                    $html .= $children;
                }
                $html .= '</li>';
            }

            $html .= '</ul>';
        } else {
            $html .= '<ul>';
        
            $folder = Helper::get_folder_detail( $folder_id );
            $html .= '<li data-id="'.intval( $folder_id ).'" class="cfdoc-home-item '. (!empty($children) ? 'has-children' : '') .'">' . $this->homeIcon() . '<span>'.esc_html($folder->title).'</span>';
            $children = $this->get_children($folder_id);
            if(!empty($children)) {
                $html .= $this->downArrowIcon();
                $html .= $children;
            }
            $html .= '</li>';
            $html .= '</ul>';
        }

        $html .= $wrap ? '</div>' : '';
        return $html;
    }

    // Private function to retrieve parent folders up to the root
    private function get_parents($folder_id, $limit_parent_id = -1, &$parents = array()) {
        $folder_id = (int)$folder_id;
        $limit_parent_id = (int)$limit_parent_id;
        $folder = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM ".$this->wpdb->prefix . "catfolders WHERE id = %d", $folder_id));
        if ($folder) {
            array_unshift($parents, $folder); // Add parent to the start of the array
            if($folder_id === $limit_parent_id) {
                return $parents;
            }
            if ($folder->parent != 0) {
                $this->get_parents($folder->parent, $limit_parent_id, $parents); // Recursively get all parents
            }
        }

        return $parents;
    }

    // Private function to recursively retrieve child folders
    private function get_children($folder_id) {
        // Check if result is already cached
        if (isset($this->childrenCache[$folder_id])) {
            return $this->childrenCache[$folder_id];
        }
        // Otherwise, compute the result
        $children = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM ".$this->wpdb->prefix . "catfolders WHERE parent = %d", $folder_id));

        if (empty($children)) return '';

        $html = '<ul class="children">';
        foreach ($children as $child) {
            $_child = $this->get_children($child->id);
            $html .= '<li class="'.(!empty($_child) ? 'has-childrend' : '').'" data-id="'.intval( $child->id ).'"><span>'.esc_html($child->title).'</span>';
            $html .=  $_child;
            $html .= '</li>';
        }
        $html .= '</ul>';

        // Cache the result
        $this->childrenCache[$folder_id] = $html;
        return $html;
    }
    private function downArrowIcon() {
        return '<i><svg aria-expanded="false" data-dropdown-placement="bottom-start" data-dropdown-toggle="859f14d8-59db-4348-8868-7456e7f7a671" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path d="M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z"></path>
        </svg></i>';
    }
    private function homeIcon() {
        return '<i class="cfdoc_breadcrumb_home_icon"><svg fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M10,20V14H14V20H19V12H22L12,3L2,12H5V20H10Z"></path></svg></i>';
    }
    public function get_lv1_children($folder_id, $wrap = true) {
        $children = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM ".$this->wpdb->prefix . "catfolders WHERE parent = %d", $folder_id));
        $html = $wrap ? '<div class="cfdoc_children">' : '';
        $html .= '<ul>';
        if($children) {
            foreach($children as $child) {
                $html .= '<li data-id="'.intval( $child->id ).'"><i>
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M10,4H4C2.89,4 2,4.89 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V8C22,6.89 21.1,6 20,6H12L10,4Z"></path>
                    </svg>
                </i>'.esc_html( $child->title ).'</li>';
            }
        }
        $html .= '</ul>';
        $html .= $wrap ? '</div>' : '';
        return $html;
    }
}
