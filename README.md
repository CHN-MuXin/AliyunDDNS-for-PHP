# 阿里云动态解析使用方法

## 配置定时启动解析程序

## Windows

1.打开系统的 任务计划程序

打开 创建任务

常规  名称随便填

触发器  新建 按照您的需求设置触发器

操作-新建操作-操作（启动程序）-设置 程序和脚本

- 程序  选择php.exe 的全路径
- 参数  index.php 的全路径

## Linux


修改 /etc/crontab 文件尾部添加定时任务


0 * * * * root php /data/AliyunDNS/index.php


上面的定时任务是 每小时 以root用户 执行一次域名解析任务



## 配置阿里云信息以及域名信息

修改index.php 文件 填入您的信息即可
