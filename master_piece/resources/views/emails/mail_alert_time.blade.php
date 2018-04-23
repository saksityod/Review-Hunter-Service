<h3>ระบบ <b>Review Hunter</b></h3>
ชื่อเคส  <b>{{ $patient_name }}</b>   
<br/>
หัตถการ : <b>{{ $procedure_name }}</b>
<br/>
HN : <b>{{$hn_no}}</b>
<br/>
VN : <b>{{$vn_no}}</b>
<br/>
มีรูปภาพสำหรับเขียนรีวิวพร้อมแล้วที่ Folder : 

@foreach ($folder_screen_name as $index => $name)
<b style="margin-right: 20px; color:red"><u>{{ $index+1 }}.{{ $name }}</u></b>

@endforeach
<br/>
<i style="color: red">*กรุณาเข้าสู่ระบบเพื่อดูรายละเอียด</i>
