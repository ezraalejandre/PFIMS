import 'package:flutter/material.dart';
import '../widgets/app_bottom_nav_bar.dart';
import '../widgets/app_header.dart';


const Color kDarkPill = Color(0xFF14161F);
const Color kAtRisk = Color(0xFFE08A2C);
const Color kOnTrack = Color(0xFF1F9254);
const Color kDelayedRed = Color(0xFFE5483B);

/// ---------------------------------------------------------------------
/// SAMPLE DATA — hard-coded for the UI build-out. Replace with real
/// project records once the backend is connected.
/// ---------------------------------------------------------------------
class _ProjectData {
  final String name;
  final String client;
  final String tag;
  final Color tagBg;
  final Color tagText;
  final double percent; // 0-1
  final Color progressColor;
  final String startDate;
  final String endDate;
  final String duration;
  final String? status; // null when there's nothing to flag (e.g. complete)
  final Color? statusColor;

  const _ProjectData({
    required this.name,
    required this.client,
    required this.tag,
    required this.tagBg,
    required this.tagText,
    required this.percent,
    required this.progressColor,
    required this.startDate,
    required this.endDate,
    required this.duration,
    this.status,
    this.statusColor,
  });
}

const Color _structureBg = Color(0xFFFBE3F2);
const Color _structureText = Color(0xFFC0388F);
const Color _finishingBg = Color(0xFFE1F6E8);
const Color _finishingText = kOnTrack;
const Color _completeBg = Color(0xFFEDEDED);
const Color _completeText = Color(0xFF6B7280);

const List<_ProjectData> _projects = [
  _ProjectData(
    name: "Skyline Tower",
    client: "Mega Realty Corp",
    tag: "Structure",
    tagBg: _structureBg,
    tagText: _structureText,
    percent: 0.72,
    progressColor: kAtRisk,
    startDate: "Jan 15, 2025",
    endDate: "Dec 30, 2025",
    duration: "Duration: 11.5 mo",
    status: "At Risk",
    statusColor: kAtRisk,
  ),
  _ProjectData(
    name: "Harbor Bridge Annex",
    client: "City Gov — NCR",
    tag: "Finishing",
    tagBg: _finishingBg,
    tagText: _finishingText,
    percent: 0.91,
    progressColor: kOnTrack,
    startDate: "Mar 1, 2025",
    endDate: "Aug 15, 2025",
    duration: "Duration: 5.5 mo",
    status: "On Track",
    statusColor: kOnTrack,
  ),
  _ProjectData(
    name: "Green Hills Residences",
    client: "Verde Homes Inc.",
    tag: "Complete",
    tagBg: _completeBg,
    tagText: _completeText,
    percent: 1.0,
    progressColor: kOnTrack,
    startDate: "Jan 5, 2025",
    endDate: "May 20, 2025",
    duration: "Duration: 4.5 mo",
    status: "Completed",
    statusColor: _completeText,
  ),
  _ProjectData(
    name: "Northgate Logistics Hub",
    client: "Pacific Storage Ltd.",
    tag: "Structure",
    tagBg: _structureBg,
    tagText: _structureText,
    percent: 0.34,
    progressColor: kOnTrack,
    startDate: "Feb 1, 2025",
    endDate: "Nov 30, 2025",
    duration: "Duration: 10 mo",
    status: "On Track",
    statusColor: kOnTrack,
  ),
  _ProjectData(
    name: "Lakeside Mixed-Use Development",
    client: "Horizon Properties",
    tag: "Finishing",
    tagBg: _finishingBg,
    tagText: _finishingText,
    percent: 0.88,
    progressColor: kAtRisk,
    startDate: "Apr 10, 2025",
    endDate: "Oct 5, 2025",
    duration: "Duration: 6 mo",
    status: "At Risk",
    statusColor: kAtRisk,
  ),
];

/// ---------------------------------------------------------------------

class ProjectTrackingScreen extends StatefulWidget {
  const ProjectTrackingScreen({super.key});

  @override
  State<ProjectTrackingScreen> createState() => _ProjectTrackingScreenState();
}

class _ProjectTrackingScreenState extends State<ProjectTrackingScreen> {
  final TextEditingController _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF2F3F5),
      appBar: const AppHeader(),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [
          // ---- Title + New Project button ----
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                "PROJECT TRACKING",
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  letterSpacing: .2,
                  color: Colors.black87,
                ),
              ),
              ElevatedButton.icon(
onPressed: () {
  
  showDialog(
    context: context,
    barrierDismissible: false,
    builder: (context) => const _NewProjectModal(),
  );
},
                style: ElevatedButton.styleFrom(
                  backgroundColor: kDarkPill,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(
                      horizontal: 14, vertical: 10),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(24),
                  ),
                ),
                icon: const Icon(Icons.add, size: 16),
                label: const Text(
                  "New Project",
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // ---- Stat tiles ----
          Row(
            children: [
              Expanded(
                child: _StatTile(
                  label: "ACTIVE PROJECTS",
                  value: "12",
                  footer: "↑ 2 this month",
                  footerColor: kOnTrack,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _StatTile(
                  label: "ON SCHEDULE",
                  value: "8",
                  footer: "67% of active",
                  footerColor: Colors.grey[600]!,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _StatTile(
                  label: "DELAYED",
                  value: "3",
                  footer: "Needs attention",
                  footerColor: kDelayedRed,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // ---- Search + filter ----
          Row(
            children: [
              Expanded(
                child: Container(
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: Colors.grey[200]!),
                  ),
                  child: TextField(
                    controller: _searchController,
                    style: const TextStyle(fontSize: 14),
                    decoration: InputDecoration(
                      hintText: "Search projects or clients...",
                      hintStyle:
                          TextStyle(color: Colors.grey[400], fontSize: 13.5),
                      prefixIcon: Icon(Icons.search,
                          color: Colors.grey[400], size: 20),
                      border: InputBorder.none,
                      contentPadding:
                          const EdgeInsets.symmetric(vertical: 14),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: Colors.grey[200]!),
                ),
                child: IconButton(
                  onPressed: () {
                    // TODO: hook up filter sheet.
                  },
                  icon: Icon(Icons.tune, color: Colors.grey[700], size: 20),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),

          Text(
            "${_projects.length} PROJECTS",
            style: TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
              color: Colors.grey[600],
              letterSpacing: .2,
            ),
          ),
          const SizedBox(height: 10),

..._projects.map(
  (p) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: _ProjectCard(
      data: p,
      onTap: () {
        showDialog(
          context: context,
          barrierDismissible: false,
          builder: (context) =>
              _ProjectDetailsModal(project: p),
        );
      },
    ),
  ),
),
        ],
      ),
      bottomNavigationBar: const AppBottomNavBar(currentIndex: 1),
    );
  }
}

class _StatTile extends StatelessWidget {
  final String label;
  final String value;
  final String footer;
  final Color footerColor;

  const _StatTile({
    required this.label,
    required this.value,
    required this.footer,
    required this.footerColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.grey[200]!),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 9.5,
              fontWeight: FontWeight.w600,
              letterSpacing: .2,
              color: Colors.grey[500],
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 6),
          Text(
            value,
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.bold,
              color: Colors.black87,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            footer,
            style: TextStyle(
                fontSize: 10.5, fontWeight: FontWeight.w600, color: footerColor),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}

class _ProjectCard extends StatelessWidget {
  final _ProjectData data;
  final VoidCallback onTap;

  const _ProjectCard({
    required this.data,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: .04),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      data.name,
                      style: const TextStyle(
                        fontSize: 15.5,
                        fontWeight: FontWeight.bold,
                        color: Colors.black87,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      data.client,
                      style: TextStyle(fontSize: 12.5, color: Colors.grey[500]),
                    ),
                  ],
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: data.tagBg,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  data.tag,
                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w600,
                    color: data.tagText,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: LinearProgressIndicator(
                    value: data.percent,
                    minHeight: 8,
                    backgroundColor: Colors.grey[200],
                    valueColor:
                        AlwaysStoppedAnimation<Color>(data.progressColor),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Text(
                "${(data.percent * 100).round()}%",
                style: TextStyle(fontSize: 12, color: Colors.grey[600]),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    "${data.startDate} → ${data.endDate}",
                    style:
                        TextStyle(fontSize: 11.5, color: Colors.grey[500]),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    data.duration,
                    style:
                        TextStyle(fontSize: 11.5, color: Colors.grey[500]),
                  ),
                ],
              ),
              if (data.status != null)
                Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 6,
                        height: 6,
                        margin: const EdgeInsets.only(right: 5),
                        decoration: BoxDecoration(
                          color: data.statusColor,
                          shape: BoxShape.circle,
                        ),
                      ),
                      Text(
                        data.status!,
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: data.statusColor,
                        ),
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ],
      ),
    ),
    );
  }
}

class _NewProjectModal extends StatefulWidget {
  const _NewProjectModal();

  @override
  State<_NewProjectModal> createState() => _NewProjectModalState();
}

class _NewProjectModalState extends State<_NewProjectModal> {

  final TextEditingController _projectController =
      TextEditingController();

  final TextEditingController _clientController =
      TextEditingController();


  @override
  void dispose() {
    _projectController.dispose();
    _clientController.dispose();
    super.dispose();
  }


  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 28),
      child: Container(
        padding: const EdgeInsets.fromLTRB(28, 24, 28, 28),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),

        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [

            // Header
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [

                const Text(
                  "Add new project",
                  style: TextStyle(
                    fontSize: 30,
                    fontWeight: FontWeight.bold,
                    color: Colors.black,
                  ),
                ),

InkWell(
  onTap: (){
    Navigator.of(context).popUntil(
      (route) => route.isFirst,
    );
  },
                  child: const Icon(
                    Icons.close,
                    size: 26,
                  ),
                )
              ],
            ),


            const SizedBox(height: 24),


            // Steps indicator
            Row(
              children: [

                _stepCircle(
                  "1",
                  true,
                ),

                Expanded(
                  child: Container(
                    height: 1,
                    color: Colors.grey[300],
                  ),
                ),


                _stepCircle(
                  "2",
                  false,
                ),


                Expanded(
                  child: Container(
                    height: 1,
                    color: Colors.grey[300],
                  ),
                ),


                _stepCircle(
                  "3",
                  false,
                ),
              ],
            ),


            const SizedBox(height: 6),


            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: const [

                Text(
                  "Project info",
                  style: TextStyle(
                    color: Colors.grey,
                    fontSize: 14,
                  ),
                ),

                Text(
                  "Team & Schedule",
                  style: TextStyle(
                    color: Colors.grey,
                    fontSize: 14,
                  ),
                ),

                Text(
                  "Review",
                  style: TextStyle(
                    color: Colors.grey,
                    fontSize: 14,
                  ),
                ),
              ],
            ),


            const SizedBox(height: 28),


            Row(
              children: [

                Icon(
                  Icons.menu,
                  size: 18,
                  color: Colors.grey[400],
                ),

                const SizedBox(width: 8),

                Text(
                  "BASIC INFORMATION",
                  style: TextStyle(
                    color: Colors.grey[400],
                    fontWeight: FontWeight.w600,
                    letterSpacing: .3,
                  ),
                )
              ],
            ),


            const SizedBox(height: 18),


            _inputLabel("Project name *"),


            const SizedBox(height: 8),

            _input(
              controller: _projectController,
              hint: "e.g. Skyline Tower Phase 2",
            ),


            const SizedBox(height: 18),


            _inputLabel("Client name *"),


            const SizedBox(height: 8),


            _input(
              controller: _clientController,
              hint: "e.g. Mega Realty Corporation",
            ),


            const SizedBox(height: 32),


            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [

                OutlinedButton(
                  onPressed: (){
                    Navigator.pop(context);
                  },

                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 18,
                      vertical: 14,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),

                  child: const Text(
                    "Cancel",
                    style: TextStyle(
                      fontSize: 16,
                      color: Colors.black54,
                    ),
                  ),
                ),



ElevatedButton(
onPressed: () {

  showDialog(
    context: context,
    barrierDismissible: false,
    builder: (_) =>
        const _ProjectTeamScheduleModal(),
  );

},

  style: ElevatedButton.styleFrom(
    backgroundColor: const Color(0xff303030),
    foregroundColor: Colors.white,

    padding: const EdgeInsets.symmetric(
      horizontal: 22,
      vertical: 14,
    ),

    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(12),
    ),
  ),

  child: const Row(
    children: [

      Text(
        "Continue",
        style: TextStyle(
          fontSize: 16,
        ),
      ),

      SizedBox(width: 8),

      Icon(
        Icons.arrow_forward,
        size: 18,
      )

    ],
  ),
),
              ],
            )
          ],
        ),
      ),
    );
  }



  Widget _stepCircle(String text, bool active){

    return Container(
      width: 24,
      height: 24,

      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: active
            ? const Color(0xffff8a2b)
            : Colors.white,

        border: Border.all(
          color: active
              ? const Color(0xffff8a2b)
              : Colors.grey[300]!,
        ),
      ),

      child: Center(
        child: Text(
          text,
          style: TextStyle(
            fontSize: 12,
            color: active
                ? Colors.white
                : Colors.grey,
          ),
        ),
      ),
    );
  }



  Widget _inputLabel(String text){

    return Text(
      text,
      style: const TextStyle(
        fontSize: 15,
        fontWeight: FontWeight.w500,
      ),
    );
  }



  Widget _input({
    required TextEditingController controller,
    required String hint,
  }){

    return TextField(
      controller: controller,

      decoration: InputDecoration(

        hintText: hint,

        hintStyle: TextStyle(
          color: Colors.grey[400],
        ),

        filled: true,
        fillColor: Colors.white,

        contentPadding:
            const EdgeInsets.symmetric(
              horizontal: 16,
              vertical: 16,
            ),

        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(
            color: Colors.grey[300]!,
          ),
        ),

        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(
            color: Colors.grey[300]!,
          ),
        ),
      ),
    );
  }
}

class _ProjectTeamScheduleModal extends StatefulWidget {
  const _ProjectTeamScheduleModal();

  @override
  State<_ProjectTeamScheduleModal> createState() =>
      _ProjectTeamScheduleModalState();
}


class _ProjectTeamScheduleModalState
    extends State<_ProjectTeamScheduleModal> {


final TextEditingController _workersController =
    TextEditingController(text: "0");

final TextEditingController _managerController =
    TextEditingController();

DateTime? _startDate;
DateTime? _endDate;


@override
void dispose() {
  _workersController.dispose();
  _managerController.dispose();
  super.dispose();
}

Future<void> _pickDate(bool start) async {

  final picked = await showDatePicker(
    context: context,
    initialDate: DateTime.now(),
    firstDate: DateTime(2020),
    lastDate: DateTime(2035),
  );

  if (picked != null) {
    setState(() {

      if(start){
        _startDate = picked;
      }else{
        _endDate = picked;
      }

    });
  }
}

  @override
  Widget build(BuildContext context) {

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 28),

      child: Container(

        padding: const EdgeInsets.fromLTRB(28, 24, 28, 28),

        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),


        child: Column(

          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [


            // HEADER
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [

                const Text(
                  "Add new project",
                  style: TextStyle(
                    fontSize: 30,
                    fontWeight: FontWeight.bold,
                  ),
                ),


InkWell(
  onTap: (){
    Navigator.of(context).popUntil(
      (route) => route.isFirst,
    );
  },

                  child: const Icon(
                    Icons.close,
                    size: 26,
                  ),
                )

              ],
            ),



            const SizedBox(height:24),



            // STEPPER
            Row(
              children: [

                _step("✓", true),

                _line(true),

                _step("2", true),

                _line(false),

                _step("3", false),

              ],
            ),



            const SizedBox(height:6),



            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,

              children: const [

                Text(
                  "Project info",
                  style: TextStyle(
                    color: Colors.grey,
                  ),
                ),

                Text(
                  "Team & Schedule",
                  style: TextStyle(
                    color: Colors.grey,
                  ),
                ),

                Text(
                  "Review",
                  style: TextStyle(
                    color: Colors.grey,
                  ),
                ),

              ],
            ),




            const SizedBox(height:28),




            Row(
              children: [

                Icon(
                  Icons.groups,
                  size:18,
                  color: Colors.grey[400],
                ),

                const SizedBox(width:8),


                Text(
                  "TEAM ASSIGNMENT",
                  style: TextStyle(
                    color: Colors.grey[400],
                    fontWeight: FontWeight.w600,
                  ),
                )

              ],
            ),



            const SizedBox(height:18),



            Row(
              children: [

                Expanded(
                  child: _field(
                    label:"Project manager *",
child:_input(
  controller: _managerController,
  hint:"Enter project manager",
),
                  ),
                ),


                const SizedBox(width:20),


                Expanded(
                  child:_field(
                    label:"No. of workers",

                    child:_input(
                      controller:_workersController,
                      hint:"0",
                    ),
                  ),
                )

              ],
            ),




            const SizedBox(height:28),




            Row(
              children: [

                Icon(
                  Icons.access_time,
                  size:18,
                  color:Colors.grey[400],
                ),


                const SizedBox(width:8),


                Text(
                  "TIMELINE",
                  style:TextStyle(
                    color:Colors.grey[400],
                    fontWeight:FontWeight.w600,
                  ),
                )

              ],
            ),



            const SizedBox(height:18),




            Row(
              children: [


                Expanded(
                  child:_field(
                    label:"Start date *",
                    child:_dateField(
                        date: _startDate,
                        onTap: () => _pickDate(true),
                    ),
                  ),
                ),



                const SizedBox(width:20),



                Expanded(
                  child:_field(
                    label:"Estimated end date *",
                    child:_dateField(
                        date: _endDate,
                        onTap: () => _pickDate(false),
                    ),
                  ),
                )

              ],
            ),





            const SizedBox(height:32),





            Row(
              mainAxisAlignment:MainAxisAlignment.spaceBetween,

              children: [


                OutlinedButton(
                  onPressed: (){
                    Navigator.pop(context);
                  },

                  style:OutlinedButton.styleFrom(
                    padding:const EdgeInsets.symmetric(
                      horizontal:16,
                      vertical:14,
                    ),
                    shape:RoundedRectangleBorder(
                      borderRadius:BorderRadius.circular(12),
                    ),
                  ),

                  child:const Text(
                    "Cancel",
                    style:TextStyle(
                      color:Colors.black54,
                      fontSize:16,
                    ),
                  ),
                ),





                Row(
                  children: [


ElevatedButton(
  onPressed: () {
    Navigator.pop(context);

//     Future.delayed(const Duration(milliseconds: 100), () {
//       showDialog(
//         context: context,
//         barrierDismissible: false,
// builder: (_) =>
//           _ProjectReviewModal(
//             manager:_managerController.text,
//             workers:_workersController.text,
//             startDate:_startDate,
//             endDate:_endDate,
//           ),
//       );
//     });
  },


                      style:ElevatedButton.styleFrom(
                        backgroundColor:kDarkPill,
                        foregroundColor:Colors.white,

                        padding:const EdgeInsets.symmetric(
                          horizontal:20,
                          vertical:14,
                        ),

                        shape:RoundedRectangleBorder(
                          borderRadius:BorderRadius.circular(12),
                        ),
                      ),


                      child:const Row(
                        children:[

                          Icon(
                            Icons.arrow_back,
                            size:18,
                          ),

                          SizedBox(width:6),

                          Text("Back")

                        ],
                      ),
                    ),



                    const SizedBox(width:12),




                    ElevatedButton(
onPressed:(){

  showDialog(
    context: context,
    barrierDismissible:false,
    builder: (_) =>
    _ProjectReviewModal(
      manager: _managerController.text,
      workers: _workersController.text,
      startDate: _startDate,
      endDate: _endDate,
    ),
  );

},

                      style:ElevatedButton.styleFrom(
                        backgroundColor:kDarkPill,
                        foregroundColor:Colors.white,

                        padding:const EdgeInsets.symmetric(
                          horizontal:20,
                          vertical:14,
                        ),

                        shape:RoundedRectangleBorder(
                          borderRadius:BorderRadius.circular(12),
                        ),
                      ),


                      child:const Row(
                        children:[

                          Text("Continue"),

                          SizedBox(width:6),

                          Icon(
                            Icons.arrow_forward,
                            size:18,
                          )

                        ],
                      ),
                    )

                  ],
                )

              ],
            )

          ],
        ),
      ),
    );
  }





  Widget _step(String text,bool active){

    return Container(

      width:24,
      height:24,

      decoration:BoxDecoration(

        shape:BoxShape.circle,

        color:active
            ? const Color(0xffff8a2b)
            : Colors.white,

        border:Border.all(
          color:active
              ? const Color(0xffff8a2b)
              : Colors.grey[300]!,
        ),
      ),


      child:Center(
        child:Text(
          text,
          style:TextStyle(
            fontSize:12,
            color:active
                ? Colors.white
                : Colors.grey,
          ),
        ),
      ),
    );
  }





  Widget _line(bool active){

    return Expanded(
      child:Container(
        height:1,
        color:active
            ? const Color(0xffff8a2b)
            : Colors.grey[300],
      ),
    );
  }





  Widget _field({
    required String label,
    required Widget child,
  }){

    return Column(
      crossAxisAlignment:CrossAxisAlignment.start,
      children:[

        Text(
          label,
          style:const TextStyle(
            fontSize:15,
            color:Colors.black87,
          ),
        ),

        const SizedBox(height:8),

        child

      ],
    );
  }





  Widget _input({
    required TextEditingController controller,
    required String hint,
  }){

    return TextField(
      controller:controller,

      decoration:InputDecoration(

        hintText:hint,

        hintStyle:TextStyle(
          color:Colors.grey[400],
        ),

        contentPadding:
        const EdgeInsets.symmetric(
          horizontal:16,
          vertical:15,
        ),


        border:OutlineInputBorder(
          borderRadius:BorderRadius.circular(10),
        ),

        enabledBorder:OutlineInputBorder(
          borderRadius:BorderRadius.circular(10),
          borderSide:BorderSide(
            color:Colors.grey[300]!,
          ),
        ),

      ),
    );
  }


Widget _dateField({
  required DateTime? date,
  required VoidCallback onTap,
}){

return InkWell(
  onTap: onTap,

  child: Container(

    height:50,

    padding:
    const EdgeInsets.symmetric(horizontal:14),

    decoration:BoxDecoration(

      border:Border.all(
        color:Colors.grey[300]!,
      ),

      borderRadius:BorderRadius.circular(10),

    ),

    child:Row(

      mainAxisAlignment:
      MainAxisAlignment.spaceBetween,

      children:[

        Text(
          date == null
          ? "mm/dd/yy"
          : "${date.month}/${date.day}/${date.year}",

          style:TextStyle(
            color: date == null
            ? Colors.grey[400]
            : Colors.black87,
          ),
        ),


        const Icon(
          Icons.calendar_month,
          size:20,
        )

      ],
    ),
  ),
);

}

}

class _ProjectReviewModal extends StatelessWidget {

final String manager;
final String workers;
final DateTime? startDate;
final DateTime? endDate;

const _ProjectReviewModal({
  required this.manager,
  required this.workers,
  required this.startDate,
  required this.endDate,
});


  @override
  Widget build(BuildContext context) {

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 28),

      child: Container(

        padding:
            const EdgeInsets.fromLTRB(28,24,28,28),

        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),


        child: Column(

          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [



            // HEADER
            Row(

              mainAxisAlignment:
                  MainAxisAlignment.spaceBetween,

              children: [

                const Text(
                  "Add new project",
                  style: TextStyle(
                    fontSize:30,
                    fontWeight:FontWeight.bold,
                  ),
                ),


InkWell(
  onTap: (){
    Navigator.of(context).popUntil(
      (route) => route.isFirst,
    );
  },

                  child:const Icon(
                    Icons.close,
                    size:26,
                  ),
                )

              ],
            ),




            const SizedBox(height:24),




            // STEPPER
            Row(

              children:[


                _step("✓",true),


                _line(),


                _step("✓",true),


                _line(),


                _step("3",true),


              ],

            ),




            const SizedBox(height:6),




            Row(

              mainAxisAlignment:
                  MainAxisAlignment.spaceBetween,

              children:const[


                Text(
                  "Project info",
                  style:TextStyle(
                    color:Colors.grey,
                  ),
                ),


                Text(
                  "Team & Schedule",
                  style:TextStyle(
                    color:Colors.grey,
                  ),
                ),


                Text(
                  "Review",
                  style:TextStyle(
                    color:Colors.grey,
                  ),
                ),

              ],
            ),





            const SizedBox(height:28),





            Container(

              width:double.infinity,

              padding:
                  const EdgeInsets.all(26),


              decoration:BoxDecoration(

                border:
                Border.all(
                  color:Colors.grey[300]!,
                ),

                borderRadius:
                BorderRadius.circular(12),

              ),



              child:Column(

                crossAxisAlignment:
                CrossAxisAlignment.start,


                children:[


                  const Text(
                    "SUMMARY",
                    style:TextStyle(
                      fontSize:18,
                      color:Colors.black87,
                    ),
                  ),



                  const SizedBox(height:22),



                  _summaryRow(
                    "Project name",
                    "Skyline Tower Phase 2",
                  ),


                  _summaryRow(
                    "Client",
                    "Mega Realty Corp",
                  ),


_summaryRow(
  "Project manager",
  manager.isEmpty ? "-" : manager,
),


_summaryRow(
  "No. of workers",
  workers,
),


_summaryRow(
  "Start date",
  startDate == null
      ? "mm/dd/yy"
      : "${startDate!.month}/${startDate!.day}/${startDate!.year}",
),


_summaryRow(
  "Estimated end date",
  endDate == null
      ? "mm/dd/yy"
      : "${endDate!.month}/${endDate!.day}/${endDate!.year}",
),


                ],
              ),
            ),





            const SizedBox(height:32),






            Row(

              mainAxisAlignment:
              MainAxisAlignment.spaceBetween,


              children:[



                OutlinedButton(

                  onPressed:(){

                    Navigator.pop(context);

                  },


                  style:OutlinedButton.styleFrom(

                    padding:
                    const EdgeInsets.symmetric(
                      horizontal:18,
                      vertical:14,
                    ),

                    shape:
                    RoundedRectangleBorder(
                      borderRadius:
                      BorderRadius.circular(12),
                    ),

                  ),


                  child:
                  const Text(
                    "Cancel",
                    style:TextStyle(
                      color:Colors.black54,
                      fontSize:16,
                    ),
                  ),
                ),






                Row(

                  children:[



ElevatedButton(
  onPressed: () {
    Navigator.pop(context);
  },

  style: ElevatedButton.styleFrom(
    backgroundColor: kDarkPill,
    foregroundColor: Colors.white,

    padding: const EdgeInsets.symmetric(
      horizontal:20,
      vertical:14,
    ),

    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(12),
    ),
  ),

  child: const Row(
    children:[
      Icon(
        Icons.arrow_back,
        size:18,
      ),

      SizedBox(width:6),

      Text("Back")
    ],
  ),
),




                    const SizedBox(width:12),





                    ElevatedButton(

onPressed: () {

  Navigator.of(context).popUntil(
    (route) => route.isFirst,
  );

},


                      style:
                      ElevatedButton.styleFrom(

                        backgroundColor:kDarkPill,
                        foregroundColor:Colors.white,

                        padding:
                        const EdgeInsets.symmetric(
                          horizontal:22,
                          vertical:14,
                        ),

                        shape:
                        RoundedRectangleBorder(
                          borderRadius:
                          BorderRadius.circular(12),
                        ),

                      ),


                      child:
                      const Text(
                        "Save project",
                        style:TextStyle(
                          fontSize:16,
                        ),
                      ),

                    )

                  ],
                )

              ],
            )

          ],
        ),
      ),
    );
  }






  Widget _step(String text,bool active){

    return Container(

      width:24,
      height:24,


      decoration:BoxDecoration(

        shape:BoxShape.circle,

        color:
        active
        ? const Color(0xffff8a2b)
        : Colors.white,


        border:
        Border.all(
          color:
          active
          ? const Color(0xffff8a2b)
          : Colors.grey[300]!,
        ),

      ),


      child:Center(

        child:Text(

          text,

          style:TextStyle(

            fontSize:12,

            color:
            active
            ? Colors.white
            : Colors.grey,

          ),
        ),
      ),
    );
  }





  Widget _line(){

    return Expanded(

      child:Container(

        height:1,

        color:
        const Color(0xffff8a2b),

      ),
    );
  }






  Widget _summaryRow(
      String label,
      String value,
      ){

    return Padding(

      padding:
      const EdgeInsets.only(bottom:8),


      child:Row(

        children:[


          SizedBox(

            width:160,

            child:Text(

              label,

              style:
              TextStyle(
                color:Colors.grey[600],
                fontSize:16,
              ),

            ),
          ),



          Expanded(

            child:Text(

              value,

              style:
              TextStyle(
                color:Colors.grey[700],
                fontSize:16,
              ),

            ),
          )

        ],
      ),
    );
  }

}

class _ProjectDetailsModal extends StatelessWidget {

  final _ProjectData project;

  const _ProjectDetailsModal({
    required this.project,
  });


  @override
  Widget build(BuildContext context) {

    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 8),

      child: Container(

        padding:
        const EdgeInsets.fromLTRB(28,24,28,28),

        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),


        child: Column(

          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [


            // HEADER
            Row(

              mainAxisAlignment:
              MainAxisAlignment.spaceBetween,

              children: [


                Column(
                  crossAxisAlignment:
                  CrossAxisAlignment.start,

                  children: [

                    Text(
                      project.name,

                      style: const TextStyle(
                        fontSize:36,
                        fontWeight:FontWeight.bold,
                      ),
                    ),


                    const SizedBox(height:4),


                    Text(
                      project.client,

                      style: TextStyle(
                        fontSize:16,
                        color:Colors.grey[600],
                      ),
                    ),

                  ],
                ),




                Column(
                  crossAxisAlignment:
                  CrossAxisAlignment.end,

                  children: [


                    Text(
                      "${(project.percent*100).round()}%",

                      style: const TextStyle(
                        fontSize:38,
                        fontWeight:FontWeight.bold,
                        color:Color(0xffff8a2b),
                      ),
                    ),


                    Text(
                      "OVERALL PROGRESS",

                      style:TextStyle(
                        fontSize:14,
                        color:Colors.grey[600],
                      ),
                    )

                  ],
                )

              ],
            ),



            const SizedBox(height:22),



            // CLOSE
            Align(
              alignment: Alignment.topRight,
              child: InkWell(
                onTap: (){
                  Navigator.pop(context);
                },
                child: const Icon(
                  Icons.close,
                  size:26,
                ),
              ),
            ),




            const SizedBox(height:10),



            LinearProgressIndicator(
              value: project.percent,

              minHeight:10,

              backgroundColor:
              Colors.grey[200],

              valueColor:
              const AlwaysStoppedAnimation(
                Color(0xffff8a2b),
              ),
            ),



            const SizedBox(height:8),


            Row(

              mainAxisAlignment:
              MainAxisAlignment.spaceBetween,

              children:[

                Text(
                  "0%",
                  style:TextStyle(
                    color:Colors.grey[600],
                  ),
                ),

                Text(
                  "100%",
                  style:TextStyle(
                    color:Colors.grey[600],
                  ),
                ),

              ],
            ),




            const SizedBox(height:28),



            _materialTile(
              "Concrete & masonry",
              0.73,
            ),


            _materialTile(
              "Steel & rebar",
              0.79,
            ),


            _materialTile(
              "Finishing materials",
              0.25,
              gray:true,
            ),


            _materialTile(
              "Electrical & plumbing",
              0.73,
            ),

          ],
        ),
      ),
    );
  }



  Widget _materialTile(
      String title,
      double value,
      {
        bool gray=false,
      }
      ){

    return Container(

      margin:
      const EdgeInsets.only(bottom:12),

      height:46,

      padding:
      const EdgeInsets.symmetric(horizontal:16),


      decoration:BoxDecoration(

        color:Colors.grey[100],

        border:
        Border.all(
          color:Colors.grey[300]!,
        ),

        borderRadius:
        BorderRadius.circular(10),

      ),


      child:Row(

        children:[


          Expanded(

            child:Text(
              title,

              style:TextStyle(
                fontSize:16,
                color:Colors.grey[700],
              ),
            ),
          ),



          SizedBox(

            width:95,

            child:LinearProgressIndicator(

              value:value,

              minHeight:5,

              backgroundColor:
              Colors.grey[300],

              valueColor:
              AlwaysStoppedAnimation(

                gray
                ? Colors.grey
                : const Color(0xffff8a2b),

              ),

            ),
          ),




          const SizedBox(width:18),



          Text(
            "${(value*100).round()}%",

            style:TextStyle(
              color:
              gray
              ? Colors.grey
              : const Color(0xffff8a2b),

              fontWeight:
              FontWeight.w600,
            ),
          ),



          const SizedBox(width:15),



          const Icon(
            Icons.keyboard_arrow_down,
            color:Colors.grey,
          )

        ],
      ),
    );
  }
}