<?php

//ไฟล์นี้เป็นตัวอย่างของการสืบทอดฟังชั่นต่างๆ จาก class ModelController

class Persernel extends Model
{
    // Constructor ของคลาส Persernel
    public function __construct()
    {
        // เรียกใช้ constructor ของคลาสแม่ (Model) และส่งชื่อของตาราง "persernel"
        parent::__construct("personnel");
    }
}
